<?php

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'milestone_id' => fn () => Milestone::query()->active()->without('species')->inRandomOrder()->value('id') ?? Milestone::factory(),
            'user_id' => User::factory(),
            'player_name_snapshot' => fn (array $attributes) => User::find($attributes['user_id'])?->display_name ?? 'player',
            'status' => SubmissionStatus::Pending,
            'player_note' => null,
            'submitted_at' => now(),
        ];
    }

    public function onMilestone(string $code): static
    {
        return $this->state(fn () => ['milestone_id' => Milestone::query()->without('species')->where('code', $code)->value('id')]);
    }

    public function verified(?User $reviewer = null): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Verified,
            'reviewed_by' => $reviewer?->id ?? User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Chybí screenshot předků.', ?User $reviewer = null): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => $reviewer?->id ?? User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn () => [
            'status' => SubmissionStatus::Withdrawn,
            'withdrawn_at' => now(),
        ]);
    }
}
