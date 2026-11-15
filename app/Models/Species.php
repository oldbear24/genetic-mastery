<?php

namespace App\Models;

use App\Support\SpeciesImage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A species in the milestone catalogue, managed by admins. The code is the
 * stable identifier (it is part of milestone codes such as GIGA_MELEE_250)
 * and never changes; the name is shown as the game shows it.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 * @property string|null $image uploaded render on the evidence disk, see SpeciesArt
 */
#[Fillable(['code', 'name', 'sort_order', 'is_active', 'image'])]
class Species extends Model
{
    protected $table = 'species';

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** URL of the uploaded render, else of the bundled one, else null. */
    public function imageUrl(): ?string
    {
        return SpeciesImage::for($this);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'dinosaur', 'code');
    }

    /** @param Builder<Species> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('species.is_active', true);
    }

    /** @param Builder<Species> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
