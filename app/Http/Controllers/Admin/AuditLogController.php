<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** OPR-7: the audit log with a filter. Read only (SEC-6). */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'action' => ['nullable', 'string', Rule::in(AuditLog::ACTIONS)],
            'actor' => ['nullable', 'integer'],
            'submission' => ['nullable', 'string', 'max:16'],
        ]);

        $submissionId = null;
        if (filled($filters['submission'] ?? null)) {
            $submissionId = Submission::query()->where('public_id', mb_strtoupper(trim($filters['submission'])))->value('id') ?? 0;
        }

        $entries = AuditLog::query()
            ->with('actor')
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', $action))
            ->when($filters['actor'] ?? null, fn (Builder $q, int $actor) => $q->where('actor_id', $actor))
            ->when($submissionId !== null, fn (Builder $q) => $q->where('entity', 'submissions')->where('entity_id', $submissionId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $submissions = Submission::query()
            ->whereIn('id', $entries->getCollection()->where('entity', 'submissions')->pluck('entity_id'))
            ->with('milestone')
            ->get()
            ->keyBy('id');

        return view('admin.audit', [
            'entries' => $entries,
            'submissions' => $submissions,
            'filters' => $filters,
            'actions' => AuditLog::ACTIONS,
            'actors' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))->orderBy('display_name')->get(),
            'users' => User::query()->whereIn('id', $this->referencedUserIds($entries->getCollection()))->get()->keyBy('id'),
        ]);
    }

    /**
     * User IDs mentioned in before/after values (reviewed_by and so on), so
     * the page can show names instead of numbers.
     *
     * @param  Collection<int, AuditLog>  $entries
     * @return list<int>
     */
    private function referencedUserIds($entries): array
    {
        $ids = [];
        foreach ($entries as $entry) {
            foreach ([$entry->before ?? [], $entry->after ?? []] as $values) {
                foreach (['reviewed_by', 'revoked_by'] as $key) {
                    if (isset($values[$key])) {
                        $ids[] = (int) $values[$key];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }
}
