<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'discord_id' => (string) fake()->unique()->numberBetween(100000000000000000, 999999999999999999),
            'username' => $username,
            'global_name' => null,
            'guild_nickname' => null,
            'display_name' => $username,
            'avatar_hash' => null,
            'is_admin' => false,
            'is_member' => true,
            'roles_checked_at' => now(),
            'last_login_at' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true]);
    }

    public function named(string $name): static
    {
        return $this->state(fn () => [
            'username' => mb_strtolower($name),
            'global_name' => $name,
            'display_name' => $name,
        ]);
    }
}
