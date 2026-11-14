<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\DiscordOutbox;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\SubmissionNote;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Corrections after a decision (OPR-2 to OPR-5). Every change is one
 * transaction with its audit entry (OPR-7). Progress and Indo Potential
 * follow by themselves because they are computed from the data.
 */
class CorrectionService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ClusterProgress $progress,
    ) {}

    /**
     * OPR-2: VERIFIED → REVOKED. Claims closed as SUPERSEDED by this
     * verification go back to PENDING, Discord gets an edit and a short
     * correction (DC-3).
     *
     * @return array{submission: Submission, reopened: int, verified: int, indo: int}
     *
     * @throws DecisionRefused
     */
    public function revoke(User $admin, Submission $submission, string $reason): array
    {
        return DB::transaction(function () use ($admin, $submission, $reason) {
            Milestone::query()->whereKey($submission->milestone_id)->lockForUpdate()->first();
            $current = $this->lock($submission);

            if ($current->status !== SubmissionStatus::Verified) {
                throw new DecisionRefused(__('corrections.errors.not_verified', ['id' => $current->public_id]));
            }

            $current->forceFill([
                'status' => SubmissionStatus::Revoked,
                'revoked_by' => $admin->id,
                'revoked_at' => now(),
                'revoke_reason' => trim($reason),
            ])->save();

            $this->audit->record($admin, 'submission.revoked', $current,
                ['status' => SubmissionStatus::Verified->value, 'reviewed_by' => $current->reviewed_by],
                ['status' => SubmissionStatus::Revoked->value, 'revoked_by' => $admin->id],
                trim($reason),
            );

            $closed = Submission::query()
                ->where('superseded_by_id', $current->id)
                ->where('status', SubmissionStatus::Superseded->value)
                ->lockForUpdate()
                ->get();

            foreach ($closed as $claim) {
                $claim->forceFill(['status' => SubmissionStatus::Pending, 'superseded_by_id' => null])->save();
                $this->audit->record($admin, 'submission.reopened_after_revoke', $claim,
                    ['status' => SubmissionStatus::Superseded->value, 'superseded_by' => $current->public_id],
                    ['status' => SubmissionStatus::Pending->value],
                );
            }

            $verified = $this->progress->verifiedCount();
            $indo = $this->progress->indoPotential($verified);
            $this->queueRevocationMessages($current, $verified, $indo);

            return ['submission' => $current, 'reopened' => $closed->count(), 'verified' => $verified, 'indo' => $indo];
        });
    }

    /**
     * OPR-3: REJECTED or REVOKED → PENDING, then the normal decision. Only
     * when the milestone is still open and the player has no other pending
     * claim for it.
     *
     * @throws DecisionRefused
     */
    public function reopen(User $admin, Submission $submission): Submission
    {
        try {
            return DB::transaction(function () use ($admin, $submission) {
                Milestone::query()->whereKey($submission->milestone_id)->lockForUpdate()->first();
                $current = $this->lock($submission);

                if (! in_array($current->status, [SubmissionStatus::Rejected, SubmissionStatus::Revoked], true)) {
                    throw new DecisionRefused(__('corrections.errors.not_reopenable', ['id' => $current->public_id, 'status' => $current->status->label()]));
                }

                if (Submission::query()->verified()->where('milestone_id', $current->milestone_id)->exists()) {
                    throw new DecisionRefused(__('corrections.errors.milestone_verified'));
                }

                $before = [
                    'status' => $current->status->value,
                    'reviewed_by' => $current->reviewed_by,
                    'rejection_reason' => $current->rejection_reason,
                    'revoked_by' => $current->revoked_by,
                    'revoke_reason' => $current->revoke_reason,
                ];

                $current->forceFill([
                    'status' => SubmissionStatus::Pending,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'rejection_reason' => null,
                    'revoked_by' => null,
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ])->save();

                $this->audit->record($admin, 'submission.reopened', $current, $before, ['status' => SubmissionStatus::Pending->value]);

                return $current;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DecisionRefused(__('corrections.errors.player_has_pending'));
        }
    }

    /**
     * OPR-4: change the verifying admin of a VERIFIED submission.
     *
     * @throws DecisionRefused
     */
    public function changeVerifier(User $admin, Submission $submission, User $newVerifier): Submission
    {
        if (! $newVerifier->isAdmin()) {
            throw new DecisionRefused(__('corrections.errors.not_admin'));
        }

        return DB::transaction(function () use ($admin, $submission, $newVerifier) {
            $current = $this->lock($submission);

            if ($current->status !== SubmissionStatus::Verified) {
                throw new DecisionRefused(__('corrections.errors.not_verified', ['id' => $current->public_id]));
            }

            $before = $current->reviewed_by;
            $current->forceFill(['reviewed_by' => $newVerifier->id])->save();

            $this->audit->record($admin, 'submission.verifier_changed', $current,
                ['reviewed_by' => $before],
                ['reviewed_by' => $newVerifier->id],
            );

            return $current;
        });
    }

    /** OPR-5: a public or internal note. Plain text, escaped on output (SEC-3). */
    public function addNote(User $admin, Submission $submission, string $visibility, string $body): SubmissionNote
    {
        return DB::transaction(function () use ($admin, $submission, $visibility, $body) {
            $note = SubmissionNote::query()->create([
                'submission_id' => $submission->id,
                'author_id' => $admin->id,
                'visibility' => $visibility,
                'body' => trim($body),
                'created_at' => now(),
            ]);

            $this->audit->record($admin, 'note.added', $submission, null, [
                'note_id' => $note->id,
                'visibility' => $visibility,
                'body' => $note->body,
            ]);

            return $note;
        });
    }

    private function lock(Submission $submission): Submission
    {
        return Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * If the announcement has not gone out yet, it is simply cancelled.
     * Otherwise the original message is edited and a correction follows.
     */
    private function queueRevocationMessages(Submission $submission, int $verified, int $indo): void
    {
        $cancelled = DiscordOutbox::query()
            ->where('submission_id', $submission->id)
            ->where('kind', DiscordOutbox::KIND_VERIFIED)
            ->where('status', DiscordOutbox::STATUS_PENDING)
            ->update(['status' => DiscordOutbox::STATUS_CANCELLED, 'updated_at' => now()]);

        if ($cancelled > 0) {
            return;
        }

        $counts = [
            'submission_id' => $submission->id,
            'verified' => $verified,
            'total' => $this->progress->totalMilestones(),
            'indo' => $indo,
            'indo_max' => $this->progress->indoMax(),
        ];

        DiscordOutbox::enqueue(DiscordOutbox::KIND_REVOKED_EDIT, $submission, ['submission_id' => $submission->id]);
        DiscordOutbox::enqueue(DiscordOutbox::KIND_REVOKED_CORRECTION, $submission, $counts);
    }
}
