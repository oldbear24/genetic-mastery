<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Species;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Models\User;
use App\Services\ClusterProgress;
use App\Services\DecisionRefused;
use App\Services\Discord\OutboxSender;
use App\Services\ReviewService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Review queue and claim detail for admins (ADM-1 to ADM-7).
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    /** ADM-1: pending claims, oldest first, with the first one opened. */
    public function queue(Request $request): View|RedirectResponse
    {
        $queue = $this->pendingQueue($request->string('q')->trim()->value());
        $first = $queue->first();

        return $this->render($request, $queue, $first);
    }

    /** ADM-2: one claim with its checks, next to the queue. */
    public function show(Request $request, Submission $submission): View
    {
        $queue = $this->pendingQueue($request->string('q')->trim()->value());

        return $this->render($request, $queue, $submission);
    }

    public function approve(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('decide', $submission);

        $data = $request->validate([
            'public_note' => ['nullable', 'string', 'max:1000'],
            'confirm_later' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->reviews->approve($request->user(), $submission, $data['public_note'] ?? null, (bool) ($data['confirm_later'] ?? false));
        } catch (DecisionRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        OutboxSender::sendAfterResponse();
        $approved = $result['submission']->load(['milestone', 'user']);

        return redirect()->route('admin.queue', $request->only('q'))->with('status', __('admin.result.approved', [
            'code' => $approved->milestone->code,
            'verified' => $result['verified'],
            'total' => app(ClusterProgress::class)->totalMilestones(),
            'indo' => $result['indo'],
            'indo_max' => app(ClusterProgress::class)->indoMax(),
            'name' => $approved->user->display_name,
        ]).($result['superseded'] > 0 ? ' '.trans_choice('admin.result.superseded', $result['superseded']) : ''));
    }

    public function reject(Request $request, Submission $submission): RedirectResponse
    {
        Gate::authorize('decide', $submission);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'reason.required' => __('admin.errors.reason_required'),
        ]);

        try {
            $rejected = $this->reviews->reject($request->user(), $submission, $data['reason']);
        } catch (DecisionRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.queue', $request->only('q'))
            ->with('status', __('admin.result.rejected', ['id' => $rejected->public_id, 'name' => $submission->user->display_name]));
    }

    /**
     * Pending claims, oldest first. The filter matches species, stat
     * (names or codes) and player names; every word has to match (ADM-1).
     *
     * @return Collection<int, Submission>
     */
    private function pendingQueue(string $filter): Collection
    {
        $query = Submission::query()->pending()
            ->with(['milestone', 'user'])
            ->withCount('images')
            ->orderBy('submitted_at')
            ->orderBy('id');

        foreach (preg_split('/\s+/', mb_strtolower($filter), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $species = $this->codesMatching(Species::query()->pluck('name', 'code')->all(), $word);
            $stats = $this->codesMatching(config('genetic.stats'), $word);
            $like = '%'.addcslashes($word, '%_\\').'%';

            $query->where(function (Builder $q) use ($species, $stats, $like) {
                $q->whereHas('milestone', fn (Builder $m) => $m->whereIn('dinosaur', $species ?: ['-'])->orWhereIn('stat', $stats ?: ['-']))
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw('lower(display_name) like ?', [$like]))
                    ->orWhereRaw('lower(player_name_snapshot) like ?', [$like])
                    ->orWhereRaw('lower(public_id) like ?', [$like]);
            });
        }

        return $query->get();
    }

    /**
     * @param  array<string, string>  $catalogue  code => name
     * @return list<string>
     */
    private function codesMatching(array $catalogue, string $word): array
    {
        return array_values(array_keys(array_filter(
            $catalogue,
            fn (string $name, string $code) => str_contains(mb_strtolower($name), $word) || str_contains(mb_strtolower($code), $word),
            ARRAY_FILTER_USE_BOTH,
        )));
    }

    /** @param Collection<int, Submission> $queue */
    private function render(Request $request, Collection $queue, ?Submission $current): View
    {
        $data = [
            'queue' => $queue,
            'current' => $current,
            'filter' => $request->string('q')->value(),
            'parallelCounts' => $queue->countBy('milestone_id'),
        ];

        if ($current !== null) {
            $current->load(['milestone', 'user', 'reviewer', 'revoker', 'images', 'notes.author', 'supersededBy']);

            $parallel = Submission::query()->pending()
                ->where('milestone_id', $current->milestone_id)
                ->whereKeyNot($current->id)
                ->with('user')
                ->orderBy('submitted_at')
                ->orderBy('id')
                ->get();

            $data += [
                'parallel' => $parallel,
                'earlier' => $current->status === SubmissionStatus::Pending ? $this->reviews->earlierPending($current)->with('user')->orderBy('submitted_at')->get() : collect(),
                'duplicates' => $this->duplicates($current),
                'playerVerified' => Submission::query()->verified()->where('user_id', $current->user_id)->count(),
                'playerRejected' => Submission::query()->where('status', SubmissionStatus::Rejected->value)->where('user_id', $current->user_id)->count(),
                'canDecide' => $request->user()->can('decide', $current),
                'admins' => User::query()->where('is_admin', true)->where('is_member', true)->orderBy('display_name')->get(),
                'trail' => $trail = AuditLog::query()->with('actor')
                    ->where('entity', 'submissions')->where('entity_id', $current->id)
                    ->orderBy('created_at')->orderBy('id')->get(),
                'trailUsers' => User::query()->whereIn('id', $trail->flatMap(fn (AuditLog $e) => [
                    $e->before['reviewed_by'] ?? null, $e->after['reviewed_by'] ?? null,
                    $e->before['revoked_by'] ?? null, $e->after['revoked_by'] ?? null,
                ])->filter()->unique())->get()->keyBy('id'),
            ];
        }

        return view('admin.review', $data);
    }

    /**
     * Images of this claim whose SHA-256 appears in another submission (ADM-4).
     *
     * @return list<array{image: SubmissionImage, others: Collection<int, SubmissionImage>}>
     */
    private function duplicates(Submission $submission): array
    {
        $hashes = $submission->images->pluck('sha256')->unique();

        if ($hashes->isEmpty()) {
            return [];
        }

        $matches = SubmissionImage::query()
            ->whereIn('sha256', $hashes)
            ->where('submission_id', '!=', $submission->id)
            ->with('submission.user')
            ->get()
            ->groupBy('sha256');

        $result = [];
        foreach ($submission->images as $image) {
            if ($matches->has($image->sha256)) {
                $result[] = ['image' => $image, 'others' => $matches->get($image->sha256)];
            }
        }

        return $result;
    }
}
