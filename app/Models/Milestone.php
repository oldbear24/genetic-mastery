<?php

namespace App\Models;

use App\Enums\MilestoneState;
use App\Enums\SubmissionStatus;
use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $dinosaur
 * @property string $stat
 * @property int $required_value
 * @property int $sort_order
 * @property-read Submission|null $verifiedSubmission
 * @property-read bool|null $has_pending
 */
#[Fillable(['code', 'dinosaur', 'stat', 'required_value', 'sort_order'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'required_value' => 'integer',
            'sort_order' => 'integer',
        ];
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
        return config("genetic.species.{$this->dinosaur}", $this->dinosaur);
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
