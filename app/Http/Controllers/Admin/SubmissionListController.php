<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** OPR-1: every submission, filtered by status, milestone, player and admin. */
class SubmissionListController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'milestone' => ['nullable', 'string', 'max:64'],
            'player' => ['nullable', 'string', 'max:100'],
            'admin' => ['nullable', 'integer'],
        ]);

        $submissions = Submission::query()
            ->with(['milestone', 'user', 'reviewer', 'revoker'])
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['milestone'] ?? null, fn (Builder $q, string $code) => $q->whereHas('milestone', fn (Builder $m) => $m->where('code', $code)))
            ->when($filters['player'] ?? null, function (Builder $q, string $player) {
                $like = '%'.addcslashes(mb_strtolower($player), '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->whereHas('user', fn (Builder $u) => $u->whereRaw('lower(display_name) like ?', [$like])->orWhere('discord_id', $player))
                    ->orWhereRaw('lower(player_name_snapshot) like ?', [$like]));
            })
            ->when($filters['admin'] ?? null, fn (Builder $q, int $admin) => $q->where(fn (Builder $w) => $w->where('reviewed_by', $admin)->orWhere('revoked_by', $admin)))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.submissions', [
            'submissions' => $submissions,
            'filters' => $filters,
            'milestones' => Milestone::query()->orderBy('sort_order')->get(),
            'admins' => User::query()
                ->where('is_admin', true)
                ->orWhereIn('id', Submission::query()->whereNotNull('reviewed_by')->select('reviewed_by'))
                ->orderBy('display_name')
                ->get(),
        ]);
    }
}
