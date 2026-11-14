<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the audit log (OPR-7). Called inside the same transaction as the
 * change it describes, so a failed change leaves no audit row behind.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(?User $actor, string $action, Model $entity, ?array $before, ?array $after, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity' => $entity->getTable(),
            'entity_id' => $entity->getKey(),
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
