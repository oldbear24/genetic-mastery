<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One admin action. Rows are only ever added (SEC-6): the model refuses
 * updates and deletes, and database triggers do the same.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $action
 * @property string $entity
 * @property int $entity_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $reason
 * @property Carbon $created_at
 * @property-read User|null $actor
 */
#[Fillable(['actor_id', 'action', 'entity', 'entity_id', 'before', 'after', 'reason', 'created_at'])]
class AuditLog extends Model
{
    protected $table = 'audit_log';

    public const UPDATED_AT = null;

    /** Every action the application writes. */
    public const ACTIONS = [
        'submission.approved',
        'submission.rejected',
        'submission.superseded',
        'submission.revoked',
        'submission.reopened',
        'submission.reopened_after_revoke',
        'submission.verifier_changed',
        'note.added',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The audit log is append-only.'));
        static::deleting(fn () => throw new LogicException('The audit log is append-only.'));
    }

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function label(): string
    {
        return self::labelFor($this->action);
    }

    /** Translation keys cannot contain dots, so "note.added" becomes "note_added". */
    public static function labelFor(string $action): string
    {
        return __('audit.action.'.str_replace('.', '_', $action));
    }
}
