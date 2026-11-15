<?php

namespace App\Services;

use App\Enums\MilestoneState;
use App\Enums\SubmissionStatus;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The milestone grid as one player sees it (HR-2, HR-3, SUB-1): own
 * discoveries, own pending claim, own rejected claim, and whether a new
 * claim is possible.
 */
class PlayerMilestones
{
    public const MINE_VERIFIED = 'mine_verified';

    public const VERIFIED = 'verified';

    public const MINE_PENDING = 'mine_pending';

    public const MINE_REJECTED = 'mine_rejected';

    public const PENDING = 'pending';

    public const LOCKED = 'locked';

    /** The stat is switched off for this species: no claims, does not count. */
    public const DISABLED = 'disabled';

    /** @var array<int, array{state: string, submission: Submission|null}> */
    private array $states = [];

    /** @param Collection<int, Milestone> $milestones */
    public function __construct(public readonly User $player, public readonly Collection $milestones)
    {
        // Latest own submission per milestone decides "rejected, retry".
        $own = Submission::query()
            ->where('user_id', $player->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('milestone_id');

        foreach ($milestones as $milestone) {
            $this->states[$milestone->id] = $this->resolve($milestone, $own->get($milestone->id));
        }
    }

    public static function for(User $player, ?Collection $milestones = null): self
    {
        return new self($player, $milestones ?? app(ClusterProgress::class)->milestones());
    }

    /** Milestones outside the board (species switched off) are DISABLED. */
    public function state(Milestone $milestone): string
    {
        return $this->states[$milestone->id]['state'] ?? self::DISABLED;
    }

    /** The submission behind the player's state (own pending or rejected claim). */
    public function submission(Milestone $milestone): ?Submission
    {
        return $this->states[$milestone->id]['submission'] ?? null;
    }

    /** A new claim is possible for LOCKED and PENDING milestones without an own pending claim (HR-3, SUB-5). */
    public function claimable(Milestone $milestone): bool
    {
        return in_array($this->state($milestone), [self::LOCKED, self::PENDING, self::MINE_REJECTED], true);
    }

    /** @param Collection<int, Submission>|null $own */
    private function resolve(Milestone $milestone, ?Collection $own): array
    {
        if (! $milestone->isActive()) {
            return ['state' => self::DISABLED, 'submission' => null];
        }

        $verified = $milestone->verifiedSubmission;

        if ($verified !== null) {
            return [
                'state' => $verified->user_id === $this->player->id ? self::MINE_VERIFIED : self::VERIFIED,
                'submission' => $verified,
            ];
        }

        $pending = $own?->first(fn (Submission $s) => $s->status === SubmissionStatus::Pending);

        if ($pending !== null) {
            return ['state' => self::MINE_PENDING, 'submission' => $pending];
        }

        $latest = $own?->first();

        if ($latest !== null && $latest->status === SubmissionStatus::Rejected) {
            return ['state' => self::MINE_REJECTED, 'submission' => $latest];
        }

        return [
            'state' => $milestone->state() === MilestoneState::Pending ? self::PENDING : self::LOCKED,
            'submission' => null,
        ];
    }
}
