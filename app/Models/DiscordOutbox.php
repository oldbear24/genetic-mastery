<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A Discord message waiting to be sent (DC-4). Rows are written in the same
 * transaction as the admin decision and sent by the scheduler.
 *
 * @property int $id
 * @property string $kind
 * @property int|null $submission_id
 * @property array<string, mixed> $payload
 * @property string $status pending|sending|sent|failed|cancelled
 * @property int $attempts
 * @property string|null $last_error
 * @property string|null $message_id
 * @property Carbon $available_at
 * @property Carbon|null $sent_at
 * @property-read Submission|null $submission
 */
#[Fillable(['kind', 'submission_id', 'payload', 'status', 'attempts', 'last_error', 'message_id', 'available_at', 'sent_at'])]
class DiscordOutbox extends Model
{
    protected $table = 'discord_outbox';

    public const KIND_VERIFIED = 'milestone_verified';

    public const KIND_REVOKED_EDIT = 'verification_revoked_edit';

    public const KIND_REVOKED_CORRECTION = 'verification_revoked_correction';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /** A verified announcement that was revoked before it was sent. */
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /** @param array<string, mixed> $payload */
    public static function enqueue(string $kind, ?Submission $submission, array $payload): self
    {
        return self::query()->create([
            'kind' => $kind,
            'submission_id' => $submission?->id,
            'payload' => $payload,
            'status' => self::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => now(),
        ]);
    }
}
