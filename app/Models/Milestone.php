<?php

namespace App\Models;

use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $code
 * @property string $dinosaur
 * @property string $stat
 * @property int $required_value
 * @property int $sort_order
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
