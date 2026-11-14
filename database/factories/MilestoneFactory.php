<?php

namespace Database\Factories;

use App\Models\Milestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tests normally use the real seed (MilestoneSeeder). The factory exists for
 * isolated cases that need a milestone outside the catalogue.
 *
 * @extends Factory<Milestone>
 */
class MilestoneFactory extends Factory
{
    public function definition(): array
    {
        $dinosaur = 'TEST'.fake()->unique()->numberBetween(1, 999999);
        $stat = 'HEALTH';
        $value = 250;

        return [
            'code' => Milestone::codeFor($dinosaur, $stat, $value),
            'dinosaur' => $dinosaur,
            'stat' => $stat,
            'required_value' => $value,
            'sort_order' => fake()->numberBetween(1000, 9999),
        ];
    }
}
