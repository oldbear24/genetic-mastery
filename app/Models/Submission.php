<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $public_id
 * @property int $milestone_id
 * @property int $user_id
 * @property string $player_name_snapshot
 * @property SubmissionStatus $status
 * @property string|null $player_note
 * @property Carbon $submitted_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property int|null $revoked_by
 * @property Carbon|null $revoked_at
 * @property string|null $revoke_reason
 * @property Carbon|null $withdrawn_at
 * @property int|null $superseded_by_id
 * @property string|null $discord_message_id
 * @property-read Milestone $milestone
 * @property-read User $user
 * @property-read User|null $reviewer
 * @property-read User|null $revoker
 * @property-read Collection<int, SubmissionImage> $images
 */
#[Fillable([
    'milestone_id', 'user_id', 'player_name_snapshot', 'status', 'player_note', 'submitted_at',
    'reviewed_by', 'reviewed_at', 'rejection_reason', 'revoked_by', 'revoked_at', 'revoke_reason',
    'withdrawn_at', 'superseded_by_id', 'discord_message_id',
])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Human readable ID derived from the primary key, e.g. S-000123.
        static::created(function (Submission $submission) {
            $submission->public_id = self::formatPublicId($submission->id);
            $submission->saveQuietly();
        });
    }

    public static function formatPublicId(int $id): string
    {
        return sprintf('S-%06d', $id);
    }

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(SubmissionImage::class)->orderBy('position');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'superseded_by_id');
    }

    /** @param Builder<Submission> $query */
    public function scopeStatus(Builder $query, SubmissionStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /** @param Builder<Submission> $query */
    public function scopeVerified(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Verified->value);
    }

    /** @param Builder<Submission> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubmissionStatus::Pending->value);
    }

    public function hasStatus(SubmissionStatus ...$statuses): bool
    {
        return in_array($this->status, $statuses, true);
    }

    /** True when the player was renamed since sending (HIS-3). */
    public function playerWasRenamed(): bool
    {
        return $this->player_name_snapshot !== $this->user->display_name;
    }
}
