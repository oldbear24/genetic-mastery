<?php

use App\Models\Milestone;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| Feature tests run against a fresh database (SQLite in memory locally,
| PostgreSQL in the second CI job) with the milestone catalogue seeded.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->seed(MilestoneSeeder::class);
    })
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function milestone(string $code): Milestone
{
    return Milestone::query()->where('code', $code)->firstOrFail();
}

function player(string $name = 'Joe'): User
{
    return User::factory()->named($name)->create();
}

function admin(string $name = 'Kuba'): User
{
    return User::factory()->named($name)->admin()->create();
}

/** Verify the first $count milestones in catalogue order. */
function verifyMilestones(int $count, ?User $player = null): void
{
    $player ??= player('Verifier'.uniqid());
    $reviewer = admin('Reviewer'.uniqid());

    Milestone::query()->orderBy('sort_order')->limit($count)->get()
        ->each(fn (Milestone $m) => Submission::factory()->for($player)->verified($reviewer)->create(['milestone_id' => $m->id]));
}

/**
 * Fake the bot's Get Guild Member call.
 *
 * @param  array<string, mixed>|null  $member  null = not a member (404)
 */
function fakeGuildMember(?array $member, int $status = 200): void
{
    $guild = config('services.discord.guild_id');

    Http::fake([
        "discord.com/api/v10/guilds/{$guild}/members/*" => $member === null
            ? Http::response(['message' => 'Unknown Member', 'code' => 10007], 404)
            : Http::response($member, $status),
    ]);
}

/** @return array<string, mixed> */
function guildMemberPayload(string $discordId, array $roles = [], ?string $nick = null, string $username = 'joe', ?string $globalName = 'Joe'): array
{
    return [
        'user' => ['id' => $discordId, 'username' => $username, 'global_name' => $globalName, 'avatar' => 'abc123'],
        'nick' => $nick,
        'roles' => $roles,
        'joined_at' => '2026-01-01T00:00:00.000000+00:00',
    ];
}
