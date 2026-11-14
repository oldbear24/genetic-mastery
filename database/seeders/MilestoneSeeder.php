<?php

namespace Database\Seeders;

use App\Models\Milestone;
use Illuminate\Database\Seeder;

/**
 * Creates the milestone catalogue (species × stats) from config/genetic.php.
 * Idempotent: running it again updates existing rows (matched by species
 * and stat) and never deletes anything, so submissions keep their milestone.
 */
class MilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $value = (int) config('genetic.required_value');
        $order = 0;

        foreach (array_keys(config('genetic.species')) as $dinosaur) {
            foreach (array_keys(config('genetic.stats')) as $stat) {
                $order++;

                Milestone::query()->updateOrCreate(
                    ['dinosaur' => $dinosaur, 'stat' => $stat],
                    [
                        'code' => Milestone::codeFor($dinosaur, $stat, $value),
                        'required_value' => $value,
                        'sort_order' => $order,
                    ],
                );
            }
        }
    }
}
