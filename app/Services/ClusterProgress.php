<?php

namespace App\Services;

use App\Enums\SubmissionStatus;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Everything the cluster progress is made of, always computed from the data.
 * Nothing here is stored: revoking a verification changes the numbers by
 * itself (Indo Potential = base + per_milestone × V, progress = V / total).
 * Only active milestones count (species and stat switched on by an admin).
 */
class ClusterProgress
{
    /** V: number of active milestones with a VERIFIED submission. */
    public function verifiedCount(): int
    {
        return Submission::query()->verified()->onActiveMilestone()->distinct()->count('milestone_id');
    }

    public function totalMilestones(): int
    {
        return Milestone::query()->active()->count();
    }

    public function indoPotential(?int $verified = null): int
    {
        $verified ??= $this->verifiedCount();

        return (int) config('genetic.indo.base') + (int) config('genetic.indo.per_milestone') * $verified;
    }

    public function indoMax(): int
    {
        return $this->indoPotential($this->totalMilestones());
    }

    /**
     * @return array{verified: int, total: int, percent: float, indo: int, indo_max: int, indo_percent: float}
     */
    public function summary(): array
    {
        $verified = $this->verifiedCount();
        $total = $this->totalMilestones();
        $indo = $this->indoPotential($verified);
        $indoMax = $this->indoPotential($total);

        return [
            'verified' => $verified,
            'total' => $total,
            'percent' => $total > 0 ? round($verified / $total * 100, 1) : 0.0,
            'indo' => $indo,
            'indo_max' => $indoMax,
            'indo_percent' => $indoMax > 0 ? round($indo / $indoMax * 100, 1) : 0.0,
        ];
    }

    /**
     * Milestones of active species in catalogue order with what is needed to
     * derive their state: the verified submission with its player and a
     * pending flag. Includes switched off stats (shown as "not used").
     *
     * @return Collection<int, Milestone>
     */
    public function milestones(): Collection
    {
        return Milestone::query()
            ->ofActiveSpecies()
            ->with(['verifiedSubmission.user'])
            ->withExists('pendingSubmissions as has_pending')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Milestones grouped into rows by species, in config order.
     *
     * @param  Collection<int, Milestone>|null  $milestones
     *                                                       `total` and `verified` count active milestones only.
     * @return BaseCollection<string, array{code: string, name: string, image: string|null, milestones: BaseCollection<int, Milestone>, verified: int, total: int}>
     */
    public function grid(?Collection $milestones = null): BaseCollection
    {
        $milestones ??= $this->milestones();

        return $milestones
            ->groupBy('dinosaur')
            ->map(function (BaseCollection $row, string $dinosaur) {
                $active = $row->filter(fn (Milestone $m) => $m->isActive());

                return [
                    'code' => $dinosaur,
                    'name' => $row->first()->dinosaurName(),
                    'image' => $row->first()->species?->imageUrl(),
                    'milestones' => $row->values(),
                    'verified' => $active->filter(fn (Milestone $m) => $m->verifiedSubmission !== null)->count(),
                    'total' => $active->count(),
                ];
            });
    }

    /**
     * The most recently verified milestones (HP-5).
     *
     * @return Collection<int, Submission>
     */
    public function recentDiscoveries(int $limit = 5): Collection
    {
        return Submission::query()
            ->verified()
            ->onActiveMilestone()
            ->with(['milestone', 'user'])
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Players ranked by verified discoveries. On a tie the player whose last
     * discovery was sent earlier ranks higher (LB-1). The discovery time is
     * the time the claim was sent, so a slow review does not change ranks.
     * Discoveries on switched off milestones do not count, like in progress.
     *
     * @return BaseCollection<int, array{rank: int, user: User, discoveries: int, last_discovery_at: string}>
     */
    public function leaderboard(?int $limit = null): BaseCollection
    {
        $rows = Submission::query()
            ->where('status', SubmissionStatus::Verified->value)
            ->onActiveMilestone()
            ->select('user_id')
            ->selectRaw('count(*) as discoveries')
            ->selectRaw('max(submitted_at) as last_discovery_at')
            ->groupBy('user_id')
            ->orderByDesc('discoveries')
            ->orderBy('last_discovery_at')
            ->orderBy('user_id')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->toBase()
            ->get();

        $users = User::query()->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->values()->map(fn (object $row, int $index) => [
            'rank' => $index + 1,
            'user' => $users[$row->user_id],
            'discoveries' => (int) $row->discoveries,
            'last_discovery_at' => (string) $row->last_discovery_at,
        ]);
    }

    /**
     * Rank and number of discoveries for one player, or null without any.
     *
     * @return array{rank: int, discoveries: int, tied_with: int}|null
     */
    public function standingOf(User $user): ?array
    {
        $board = $this->leaderboard();
        $entry = $board->first(fn (array $row) => $row['user']->is($user));

        if ($entry === null) {
            return null;
        }

        return [
            'rank' => $entry['rank'],
            'discoveries' => $entry['discoveries'],
            'tied_with' => $board->where('discoveries', $entry['discoveries'])->count() - 1,
        ];
    }
}
