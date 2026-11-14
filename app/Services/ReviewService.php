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
 * Admin decisions on pending claims (ADM-3, ADM-5, ADM-6, ADM-7).
 *
 * Approval is one transaction: VERIFIED, other pending claims SUPERSEDED,
 * audit log, optional public note and the Discord announcement in the
 * outbox. The unique partial index makes a second VERIFIED impossible even
 * when two admins approve at the same moment.
 */
class ReviewService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ClusterProgress $progress,
    ) {}

    /**
     * @return array{submission: Submission, superseded: int, verified: int, indo: int}
     *
     * @throws DecisionRefused
     */
    public function approve(User $admin, Submission $submission, ?string $publicNote = null, bool $confirmLater = false): array
    {
        $this->ensureMayDecide($admin, $submission);

        try {
            return DB::transaction(function () use ($admin, $submission, $publicNote, $confirmLater) {
                // PostgreSQL: serialise decisions on one milestone. SQLite holds
                // the database write lock from BEGIN IMMEDIATE already.
                Milestone::query()->whereKey($submission->milestone_id)->lockForUpdate()->first();
                $current = Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

                if ($current->status !== SubmissionStatus::Pending) {
                    throw new DecisionRefused(__('admin.errors.not_pending', ['id' => $current->public_id, 'status' => $current->status->label()]));
                }

                if (Submission::query()->verified()->where('milestone_id', $current->milestone_id)->exists()) {
                    throw new DecisionRefused(__('admin.errors.already_verified'));
                }

                if (! $confirmLater && $this->earlierPending($current)->exists()) {
                    throw new DecisionRefused(__('admin.errors.confirm_later'));
                }

                $current->forceFill([
                    'status' => SubmissionStatus::Verified,
                    'reviewed_by' => $admin->id,
                    'reviewed_at' => now(),
                ])->save();

                $this->audit->record($admin, 'submission.approved', $current,
                    ['status' => SubmissionStatus::Pending->value],
                    ['status' => SubmissionStatus::Verified->value, 'reviewed_by' => $admin->id],
                );

                $superseded = 0;
                $others = Submission::query()->pending()
                    ->where('milestone_id', $current->milestone_id)
                    ->whereKeyNot($current->id)
                    ->lockForUpdate()
                    ->get();

                foreach ($others as $other) {
                    $other->forceFill(['status' => SubmissionStatus::Superseded, 'superseded_by_id' => $current->id])->save();
                    $this->audit->record($admin, 'submission.superseded', $other,
                        ['status' => SubmissionStatus::Pending->value],
                        ['status' => SubmissionStatus::Superseded->value, 'superseded_by' => $current->public_id],
                    );
                    $superseded++;
                }

                if (filled($publicNote)) {
                    $note = SubmissionNote::query()->create([
                        'submission_id' => $current->id,
                        'author_id' => $admin->id,
                        'visibility' => SubmissionNote::PUBLIC,
                        'body' => trim($publicNote),
                        'created_at' => now(),
                    ]);
                    $this->audit->record($admin, 'note.added', $current, null, ['note_id' => $note->id, 'visibility' => $note->visibility]);
                }

                $verified = $this->progress->verifiedCount();
                $indo = $this->progress->indoPotential($verified);

                DiscordOutbox::enqueue(DiscordOutbox::KIND_VERIFIED, $current, [
                    'submission_id' => $current->id,
                    'verified' => $verified,
                    'total' => $this->progress->totalMilestones(),
                    'indo' => $indo,
                    'indo_max' => $this->progress->indoMax(),
                ]);

                return ['submission' => $current, 'superseded' => $superseded, 'verified' => $verified, 'indo' => $indo];
            });
        } catch (UniqueConstraintViolationException) {
            // Another admin verified this milestone in the meantime.
            throw new DecisionRefused(__('admin.errors.already_verified'));
        }
    }

    /** @throws DecisionRefused */
    public function reject(User $admin, Submission $submission, string $reason): Submission
    {
        $this->ensureMayDecide($admin, $submission);

        return DB::transaction(function () use ($admin, $submission, $reason) {
            $current = Submission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== SubmissionStatus::Pending) {
                throw new DecisionRefused(__('admin.errors.not_pending', ['id' => $current->public_id, 'status' => $current->status->label()]));
            }

            $current->forceFill([
                'status' => SubmissionStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            $this->audit->record($admin, 'submission.rejected', $current,
                ['status' => SubmissionStatus::Pending->value],
                ['status' => SubmissionStatus::Rejected->value, 'reviewed_by' => $admin->id],
                trim($reason),
            );

            return $current;
        });
    }

    /** Pending claims on the same milestone that were sent earlier (ADM-3). */
    public function earlierPending(Submission $submission)
    {
        return Submission::query()->pending()
            ->where('milestone_id', $submission->milestone_id)
            ->whereKeyNot($submission->id)
            ->where(fn ($q) => $q->where('submitted_at', '<', $submission->submitted_at)
                ->orWhere(fn ($q) => $q->where('submitted_at', $submission->submitted_at)->where('id', '<', $submission->id)));
    }

    /** @throws DecisionRefused */
    private function ensureMayDecide(User $admin, Submission $submission): void
    {
        if (! $admin->isAdmin()) {
            throw new DecisionRefused(__('app.errors.403_text'));
        }

        if ($submission->user_id === $admin->id && ! config('genetic.allow_self_approve')) {
            throw new DecisionRefused(__('admin.errors.own_claim'));
        }
    }
}
