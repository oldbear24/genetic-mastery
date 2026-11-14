<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubmissionRequest;
use App\Models\Milestone;
use App\Models\Submission;
use App\Services\ClusterProgress;
use App\Services\PlayerMilestones;
use App\Services\SubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SubmissionController extends Controller
{
    /** All own submissions (HR-4). */
    public function index(Request $request): View
    {
        $submissions = Submission::query()
            ->where('user_id', $request->user()->id)
            ->with(['milestone', 'reviewer'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(25);

        $milestoneIds = $submissions->getCollection()->pluck('milestone_id')->unique();

        // Milestones where a new claim is not possible: already verified, or
        // the player already has a pending claim there.
        $blocked = Submission::query()
            ->whereIn('milestone_id', $milestoneIds)
            ->where(fn ($q) => $q->verified()->orWhere(fn ($q) => $q->pending()->where('user_id', $request->user()->id)))
            ->pluck('milestone_id')
            ->flip();

        return view('submissions.index', [
            'submissions' => $submissions,
            'blocked' => $blocked,
            'otherPending' => $this->otherPendingCounts($submissions->getCollection(), $request->user()->id),
        ]);
    }

    /** New claim form (SUB-1 to SUB-3). */
    public function create(Request $request, ClusterProgress $progress): View
    {
        $milestones = $progress->milestones();
        $board = PlayerMilestones::for($request->user(), $milestones);
        $grid = $progress->grid($milestones);

        $requested = $milestones->firstWhere('code', $request->query('milestone'))
            ?? $milestones->firstWhere('id', (int) old('milestone_id'));

        if ($requested === null || ! $board->claimable($requested)) {
            $requested = $milestones->first(fn (Milestone $m) => $board->claimable($m));
        }

        return view('submissions.create', [
            'grid' => $grid,
            'board' => $board,
            'selected' => $requested,
        ]);
    }

    public function store(StoreSubmissionRequest $request, SubmissionService $service): RedirectResponse
    {
        $milestone = Milestone::query()->findOrFail($request->integer('milestone_id'));

        $submission = $service->submit(
            $request->user(),
            $milestone,
            array_values((array) $request->file('images', [])),
            $request->kinds(),
            $request->input('player_note'),
        );

        return redirect()
            ->route('submissions.show', $submission)
            ->with('submitted', true);
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        $submission->load(['milestone', 'user', 'reviewer', 'revoker', 'images']);

        return view('submissions.show', [
            'submission' => $submission,
            'otherPending' => Submission::query()->pending()
                ->where('milestone_id', $submission->milestone_id)
                ->whereKeyNot($submission->id)
                ->count(),
        ]);
    }

    /** HR-5: the author withdraws a pending claim. */
    public function withdraw(Request $request, Submission $submission, SubmissionService $service): RedirectResponse
    {
        Gate::authorize('withdraw', $submission);

        $service->withdraw($request->user(), $submission);

        return back()->with('status', __('submissions.withdraw.done', ['id' => $submission->public_id]));
    }

    /**
     * Number of other pending claims per milestone, for "1 další žádost čeká".
     *
     * @param  Collection<int, Submission>  $submissions
     * @return array<int, int>
     */
    private function otherPendingCounts($submissions, int $userId): array
    {
        $milestoneIds = $submissions->filter(fn (Submission $s) => $s->status->value === 'pending')->pluck('milestone_id')->unique();

        if ($milestoneIds->isEmpty()) {
            return [];
        }

        return Submission::query()->pending()
            ->whereIn('milestone_id', $milestoneIds)
            ->where('user_id', '!=', $userId)
            ->selectRaw('milestone_id, count(*) as aggregate')
            ->groupBy('milestone_id')
            ->pluck('aggregate', 'milestone_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
