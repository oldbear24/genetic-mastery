<?php

namespace App\Services;

use App\Enums\EvidenceKind;
use App\Enums\SubmissionStatus;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Models\User;
use App\Services\Evidence\EvidenceProcessor;
use App\Services\Evidence\InvalidEvidence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What a player can do with claims: send one (SUB-4, SUB-5) and withdraw a
 * pending one (HR-5). A sent claim is never edited (SUB-6).
 */
class SubmissionService
{
    public function __construct(private readonly EvidenceProcessor $evidence) {}

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<EvidenceKind>  $kinds
     *
     * @throws ValidationException
     */
    public function submit(User $player, Milestone $milestone, array $files, array $kinds, ?string $note): Submission
    {
        $this->ensureWithinRateLimit($player);
        $this->ensureClaimable($player, $milestone);

        $infos = [];
        foreach (array_values($files) as $i => $file) {
            try {
                $infos[$i] = $this->evidence->inspect($file);
            } catch (InvalidEvidence $e) {
                throw ValidationException::withMessages(["images.{$i}" => $e->translated()]);
            }
        }

        $stored = [];

        try {
            foreach (array_values($files) as $i => $file) {
                $stored[$i] = $this->evidence->store($file, $infos[$i]);
            }

            return DB::transaction(function () use ($player, $milestone, $stored, $kinds, $note) {
                // Serialise with approvals of the same milestone (PostgreSQL
                // row lock; SQLite already holds the write lock: IMMEDIATE).
                Milestone::query()->whereKey($milestone->id)->lockForUpdate()->first();
                $this->ensureClaimable($player, $milestone);

                $submission = Submission::query()->create([
                    'milestone_id' => $milestone->id,
                    'user_id' => $player->id,
                    'player_name_snapshot' => $player->display_name,
                    'status' => SubmissionStatus::Pending,
                    'player_note' => filled($note) ? trim($note) : null,
                    'submitted_at' => now(),
                ]);

                foreach ($stored as $i => $image) {
                    SubmissionImage::query()->create($image + [
                        'submission_id' => $submission->id,
                        'position' => $i + 1,
                        'kind' => $kinds[$i] ?? EvidenceKind::Other,
                    ]);
                }

                return $submission->refresh();
            });
        } catch (Throwable $e) {
            $this->evidence->discard(array_column($stored, 'storage_key'));

            if ($e instanceof InvalidEvidence) {
                throw ValidationException::withMessages(['images' => $e->translated()]);
            }

            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.already_pending')]);
            }

            if (! $e instanceof ValidationException) {
                Log::error('Storing a submission failed.', ['user_id' => $player->id, 'milestone' => $milestone->code, 'exception' => $e::class]);
            }

            throw $e;
        }
    }

    /**
     * PENDING → WITHDRAWN. A conditional update, so a claim an admin has
     * just decided cannot be withdrawn any more.
     *
     * @throws ValidationException
     */
    public function withdraw(User $player, Submission $submission): void
    {
        $affected = Submission::query()
            ->whereKey($submission->id)
            ->where('user_id', $player->id)
            ->where('status', SubmissionStatus::Pending->value)
            ->update([
                'status' => SubmissionStatus::Withdrawn->value,
                'withdrawn_at' => now(),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw ValidationException::withMessages(['submission' => __('submissions.withdraw.not_pending')]);
        }
    }

    /** @throws ValidationException */
    public function ensureClaimable(User $player, Milestone $milestone): void
    {
        if (! $milestone->isActive()) {
            throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.disabled')]);
        }

        if (Submission::query()->verified()->where('milestone_id', $milestone->id)->exists()) {
            throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.already_verified')]);
        }

        if (Submission::query()->pending()->where('milestone_id', $milestone->id)->where('user_id', $player->id)->exists()) {
            throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.already_pending')]);
        }
    }

    /**
     * At most N submissions per hour and per day for one player (SEC-1).
     * Counted from the stored submissions, so failed attempts do not count.
     *
     * @throws ValidationException
     */
    public function ensureWithinRateLimit(User $player): void
    {
        $limits = config('genetic.rate_limits');
        $base = Submission::query()->where('user_id', $player->id);

        if ((clone $base)->where('submitted_at', '>=', now()->subHour())->count() >= (int) $limits['submissions_per_hour']) {
            throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.rate_hour', ['max' => $limits['submissions_per_hour']])]);
        }

        if ((clone $base)->where('submitted_at', '>=', now()->subDay())->count() >= (int) $limits['submissions_per_day']) {
            throw ValidationException::withMessages(['milestone_id' => __('submissions.form.errors.rate_day', ['max' => $limits['submissions_per_day']])]);
        }
    }
}
