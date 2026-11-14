<?php

namespace App\Services\Discord;

use App\Enums\SubmissionStatus;
use App\Models\DiscordOutbox;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends queued Discord messages (DC-4, DC-5). Runs from the scheduler, never
 * from an admin request, so a Discord outage does not block decisions.
 *
 *  - a row is claimed with a conditional update (pending → sending), so two
 *    runs never send the same row
 *  - message creation carries a nonce with enforce_nonce, so a retry after a
 *    lost response returns the existing message instead of a duplicate
 *  - failures go back to pending with a growing pause; 429 honours retry_after
 *  - the message ID is stored for later edits
 */
class OutboxSender
{
    public const MAX_ATTEMPTS = 20;

    public const STUCK_AFTER_MINUTES = 5;

    public function __construct(
        private readonly DiscordClient $discord,
        private readonly DiscordMessages $messages,
    ) {}

    /**
     * With DISCORD_SEND_AFTER_RESPONSE the outbox is sent once the admin's
     * response has gone out (fastcgi_finish_request), so announcements do
     * not wait for a cron that runs only every few minutes. The decision
     * itself never waits for Discord; failures are retried by the cron.
     */
    public static function sendAfterResponse(): void
    {
        if (config('genetic.discord_send_after_response')) {
            defer(fn () => app(self::class)->run(10), 'discord-outbox');
        }
    }

    /** @return array{sent: int, retried: int, failed: int, cancelled: int, skipped: int} */
    public function run(int $limit = 20): array
    {
        $this->releaseStuck();

        $stats = ['sent' => 0, 'retried' => 0, 'failed' => 0, 'cancelled' => 0, 'skipped' => 0];

        $ids = DiscordOutbox::query()
            ->where('status', DiscordOutbox::STATUS_PENDING)
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $row = $this->claim($id);

            if ($row === null) {
                $stats['skipped']++;

                continue;
            }

            $stats[$this->process($row)]++;
        }

        return $stats;
    }

    /** Conditional update: only one runner gets the row. */
    private function claim(int $id): ?DiscordOutbox
    {
        $claimed = DiscordOutbox::query()
            ->whereKey($id)
            ->where('status', DiscordOutbox::STATUS_PENDING)
            ->update([
                'status' => DiscordOutbox::STATUS_SENDING,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        return $claimed === 1 ? DiscordOutbox::query()->find($id) : null;
    }

    /** @return 'sent'|'retried'|'failed'|'cancelled'|'skipped' */
    private function process(DiscordOutbox $row): string
    {
        $channel = (string) config('services.discord.announce_channel_id');
        $submission = Submission::query()->with(['milestone', 'user'])->find($row->submission_id);

        if ($submission === null || blank($channel)) {
            return $this->fail($row, $submission === null ? 'Submission not found.' : 'DISCORD_ANNOUNCE_CHANNEL_ID is not set.');
        }

        try {
            return match ($row->kind) {
                DiscordOutbox::KIND_VERIFIED => $this->sendAnnouncement($row, $submission, $channel),
                DiscordOutbox::KIND_REVOKED_EDIT => $this->editAnnouncement($row, $submission, $channel),
                DiscordOutbox::KIND_REVOKED_CORRECTION => $this->sendCorrection($row, $submission, $channel),
                default => $this->fail($row, "Unknown kind {$row->kind}."),
            };
        } catch (DiscordUnavailable $e) {
            return $this->retryLater($row, $e);
        } catch (Throwable $e) {
            Log::error('Discord outbox row crashed.', ['outbox_id' => $row->id, 'submission_id' => $row->submission_id, 'exception' => $e::class]);

            return $this->retryLater($row, new DiscordUnavailable($e::class.': '.$e->getMessage()));
        }
    }

    private function sendAnnouncement(DiscordOutbox $row, Submission $submission, string $channel): string
    {
        // Revoked in the meantime without the row being cancelled (it was
        // being sent at that moment): nothing to announce any more.
        if ($submission->status !== SubmissionStatus::Verified && $submission->discord_message_id === null) {
            return $this->cancel($row);
        }

        $message = $this->discord->createMessage($channel, $this->messages->announcement($submission, $this->counts($row), $this->nonce($row)));

        $submission->forceFill(['discord_message_id' => (string) $message['id']])->saveQuietly();

        return $this->markSent($row, (string) $message['id']);
    }

    private function editAnnouncement(DiscordOutbox $row, Submission $submission, string $channel): string
    {
        $original = $this->originalMessageId($row, $submission);

        if ($original === false) {
            return $this->cancel($row);
        }

        if ($original === null) {
            return $this->waitForAnnouncement($row);
        }

        try {
            $this->discord->editMessage($channel, $original, $this->messages->revokedEdit($submission));
        } catch (DiscordUnavailable $e) {
            if ($e->status === 404) {
                // The original message was deleted on Discord; nothing to edit.
                return $this->fail($row, 'Original message not found (404).');
            }

            throw $e;
        }

        return $this->markSent($row, $original);
    }

    private function sendCorrection(DiscordOutbox $row, Submission $submission, string $channel): string
    {
        $original = $this->originalMessageId($row, $submission);

        if ($original === false) {
            return $this->cancel($row);
        }

        if ($original === null) {
            return $this->waitForAnnouncement($row);
        }

        $message = $this->discord->createMessage($channel, $this->messages->correction($submission, $this->counts($row), $original, $this->nonce($row)));

        return $this->markSent($row, (string) $message['id']);
    }

    /**
     * The announcement this revocation refers to: its message ID, null while
     * it is still on its way, false when it was never sent.
     */
    private function originalMessageId(DiscordOutbox $row, Submission $submission): string|false|null
    {
        $announcement = DiscordOutbox::query()
            ->where('submission_id', $submission->id)
            ->where('kind', DiscordOutbox::KIND_VERIFIED)
            ->where('id', '<', $row->id)
            ->orderByDesc('id')
            ->first();

        if ($announcement === null) {
            return false;
        }

        return match ($announcement->status) {
            DiscordOutbox::STATUS_SENT => $announcement->message_id,
            DiscordOutbox::STATUS_PENDING, DiscordOutbox::STATUS_SENDING => null,
            default => false,
        };
    }

    /** @return array{verified: int, total: int, indo: int, indo_max: int} */
    private function counts(DiscordOutbox $row): array
    {
        $p = $row->payload;

        return [
            'verified' => (int) ($p['verified'] ?? 0),
            'total' => (int) ($p['total'] ?? 0),
            'indo' => (int) ($p['indo'] ?? 0),
            'indo_max' => (int) ($p['indo_max'] ?? 0),
        ];
    }

    /** Discord accepts nonces up to 25 characters. */
    private function nonce(DiscordOutbox $row): string
    {
        return 'gm-outbox-'.$row->id;
    }

    private function markSent(DiscordOutbox $row, string $messageId): string
    {
        $row->forceFill([
            'status' => DiscordOutbox::STATUS_SENT,
            'message_id' => $messageId,
            'sent_at' => now(),
            'last_error' => null,
        ])->save();

        return 'sent';
    }

    private function cancel(DiscordOutbox $row): string
    {
        $row->forceFill(['status' => DiscordOutbox::STATUS_CANCELLED])->save();

        return 'cancelled';
    }

    private function fail(DiscordOutbox $row, string $error): string
    {
        Log::warning('Discord outbox row failed for good.', ['outbox_id' => $row->id, 'submission_id' => $row->submission_id, 'error' => $error]);
        $row->forceFill(['status' => DiscordOutbox::STATUS_FAILED, 'last_error' => $error])->save();

        return 'failed';
    }

    private function waitForAnnouncement(DiscordOutbox $row): string
    {
        // Not a failed attempt: give the attempt back and look again shortly.
        $row->forceFill([
            'status' => DiscordOutbox::STATUS_PENDING,
            'attempts' => max(0, $row->attempts - 1),
            'available_at' => now()->addSeconds(30),
        ])->save();

        return 'retried';
    }

    private function retryLater(DiscordOutbox $row, DiscordUnavailable $e): string
    {
        if ($row->attempts >= self::MAX_ATTEMPTS) {
            return $this->fail($row, $e->getMessage());
        }

        // 30 s, 1 min, 2 min, 4 min … at most an hour; 429 uses retry_after.
        $delay = $e->retryAfter !== null
            ? (int) ceil($e->retryAfter) + 1
            : min(3600, 30 * (2 ** max(0, $row->attempts - 1)));

        Log::warning('Discord outbox send failed, will retry.', [
            'outbox_id' => $row->id,
            'submission_id' => $row->submission_id,
            'attempts' => $row->attempts,
            'status' => $e->status,
            'retry_in_seconds' => $delay,
        ]);

        $row->forceFill([
            'status' => DiscordOutbox::STATUS_PENDING,
            'last_error' => mb_substr($e->getMessage(), 0, 500),
            'available_at' => now()->addSeconds($delay),
        ])->save();

        return 'retried';
    }

    /**
     * A runner that died mid-send leaves rows in "sending". Put them back;
     * the nonce keeps Discord from creating a second message.
     */
    private function releaseStuck(): void
    {
        DiscordOutbox::query()
            ->where('status', DiscordOutbox::STATUS_SENDING)
            ->where('updated_at', '<', now()->subMinutes(self::STUCK_AFTER_MINUTES))
            ->update(['status' => DiscordOutbox::STATUS_PENDING, 'updated_at' => now()]);
    }
}
