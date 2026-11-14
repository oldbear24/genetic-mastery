<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns before/after values of an audit entry into readable Czech text:
 * "stav: Čeká na schválení → Ověřeno · ověřující admin: — → Kuba".
 */
class AuditFormatter
{
    /** @param Collection<int, User> $users users referenced by ID, keyed by ID */
    public function __construct(private readonly Collection $users) {}

    /** @return list<array{field: string, before: string, after: string}> */
    public function changes(AuditLog $entry): array
    {
        $before = $entry->before ?? [];
        $after = $entry->after ?? [];
        $keys = array_values(array_unique([...array_keys($before), ...array_keys($after)]));

        return array_map(fn (string $key) => [
            'field' => __("audit.field.{$key}"),
            'before' => $this->value($key, $before[$key] ?? null),
            'after' => $this->value($key, $after[$key] ?? null),
        ], $keys);
    }

    private function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return __('app.none');
        }

        return match ($key) {
            'status' => SubmissionStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'reviewed_by', 'revoked_by' => $this->users->get((int) $value)?->display_name ?? '#'.$value,
            'visibility' => __("audit.visibility.{$value}"),
            default => (string) $value,
        };
    }
}
