<?php

namespace App\Http\Controllers;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\ClusterProgress;
use App\Services\PlayerMilestones;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Player dashboard (HR-1 to HR-6): cluster counters, personal stats, own
 * submissions and the milestone grid with the player's own layer.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ClusterProgress $progress): View
    {
        $user = $request->user();
        $milestones = $progress->milestones();
        $board = PlayerMilestones::for($user, $milestones);

        $recent = Submission::query()
            ->where('user_id', $user->id)
            ->with(['milestone', 'reviewer'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pendingCount = $milestones->filter(fn ($m) => $board->state($m) === PlayerMilestones::MINE_PENDING)->count();
        $rejectedCount = $milestones->filter(fn ($m) => $board->state($m) === PlayerMilestones::MINE_REJECTED)->count();

        return view('dashboard', [
            'user' => $user,
            'summary' => $progress->summary(),
            'standing' => $progress->standingOf($user),
            'board' => $board,
            'grid' => $progress->grid($milestones),
            'recent' => $recent,
            'totalSubmissions' => Submission::query()->where('user_id', $user->id)->count(),
            'pendingCount' => $pendingCount,
            'rejectedCount' => $rejectedCount,
            'otherPending' => $this->otherPending($recent, $user->id),
        ]);
    }

    /**
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, int> milestone_id => other pending claims
     */
    private function otherPending($submissions, int $userId): array
    {
        $ids = $submissions->filter(fn (Submission $s) => $s->status === SubmissionStatus::Pending)->pluck('milestone_id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Submission::query()->pending()
            ->whereIn('milestone_id', $ids)
            ->where('user_id', '!=', $userId)
            ->selectRaw('milestone_id, count(*) as aggregate')
            ->groupBy('milestone_id')
            ->pluck('aggregate', 'milestone_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
