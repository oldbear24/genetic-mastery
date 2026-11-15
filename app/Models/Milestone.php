<?php

namespace App\Models;

use App\Enums\MilestoneState;
use App\Enums\SubmissionStatus;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $dinosaur
 * @property string $stat
 * @property int $required_value
 * @property int $sort_order
 * @property bool $is_active stat switched on for this species (admin setting)
 * @property-read Species|null $species
 * @property-read Submission|null $verifiedSubmission
 * @property-read bool|null $has_pending
 */
#[Fillable(['code', 'dinosaur', 'stat', 'required_value', 'sort_order', 'is_active'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    /** The species name is shown almost everywhere a milestone is. */
    protected $with = ['species'];

    protected function casts(): array
    {
        return [
            'required_value' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class, 'dinosaur', 'code');
    }

    /**
     * Milestones that count: the stat is switched on and the species is
     * active. Switched off milestones keep their claims, they just do not
     * count towards progress, Indo Potential or the leaderboard.
     *
     * @param  Builder<Milestone>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('milestones.is_active', true)
            ->whereHas('species', fn (Builder $species) => $species->where('is_active', true));
    }

    /** @param Builder<Milestone> $query */
    public function scopeOfActiveSpecies(Builder $query): void
    {
        $query->whereHas('species', fn (Builder $species) => $species->where('is_active', true));
    }

    public function isActive(): bool
    {
        return $this->is_active && ($this->species?->is_active ?? false);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** The one VERIFIED submission (the database allows at most one). */
    public function verifiedSubmission(): HasOne
    {
        return $this->hasOne(Submission::class)->where('status', SubmissionStatus::Verified->value);
    }

    public function pendingSubmissions(): HasMany
    {
        return $this->hasMany(Submission::class)->where('status', SubmissionStatus::Pending->value);
    }

    /**
     * Derived state. Expects verifiedSubmission to be loaded and has_pending
     * to be selected (withExists), see ClusterProgress::milestones().
     */
    public function state(): MilestoneState
    {
        if ($this->verified() !== null) {
            return MilestoneState::Verified;
        }

        $attributes = $this->getAttributes();
        $hasPending = array_key_exists('has_pending', $attributes)
            ? (bool) $attributes['has_pending']
            : $this->pendingSubmissions()->exists();

        return $hasPending ? MilestoneState::Pending : MilestoneState::Locked;
    }

    /** The verified submission, using the eager loaded relation when present. */
    public function verified(): ?Submission
    {
        if (! $this->relationLoaded('verifiedSubmission')) {
            $this->setRelation('verifiedSubmission', $this->verifiedSubmission()->with('user')->first());
        }

        return $this->getRelation('verifiedSubmission');
    }

    public static function codeFor(string $dinosaur, string $stat, int $requiredValue): string
    {
        return "{$dinosaur}_{$stat}_{$requiredValue}";
    }

    public function dinosaurName(): string
    {
        return $this->species->name ?? $this->dinosaur;
    }

    public function statName(): string
    {
        return config("genetic.stats.{$this->stat}", $this->stat);
    }

    /** "Giganotosaurus · Melee Damage" */
    public function title(): string
    {
        return $this->dinosaurName().' · '.$this->statName();
    }
}
