<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

function staleAdmin(int $minutesAgo = 11): User
{
    return User::factory()->admin()->create([
        'discord_id' => '555000000000000555',
        'roles_checked_at' => now()->subMinutes($minutesAgo),
    ]);
}

it('drops admin rights within 10 minutes after the role is removed on Discord (AUTH-4)', function () {
    $admin = staleAdmin(11);
    fakeGuildMember(guildMemberPayload($admin->discord_id, roles: []));

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    expect($admin->fresh()->is_admin)->toBeFalse()
        ->and(Gate::forUser($admin->fresh())->allows('admin'))->toBeFalse();
});

it('does not ask Discord again before 10 minutes have passed', function () {
    $admin = staleAdmin(5);
    Http::fake();

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    Http::assertNothingSent();
    expect($admin->fresh()->is_admin)->toBeTrue();
});

it('grants admin rights when the role is added on Discord', function () {
    $player = User::factory()->create(['discord_id' => '555000000000000556', 'roles_checked_at' => now()->subMinutes(11)]);
    fakeGuildMember(guildMemberPayload($player->discord_id, roles: ['900000000000000009']));

    $this->actingAs($player)->get(route('dashboard'))->assertOk();

    expect($player->fresh()->isAdmin())->toBeTrue();
});

it('signs out a user who left the server', function () {
    $player = User::factory()->create(['discord_id' => '555000000000000557', 'roles_checked_at' => now()->subMinutes(11)]);
    fakeGuildMember(null);

    $this->actingAs($player)->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', __('auth.discord.not_member'));

    $this->assertGuest();
    expect($player->fresh()->is_member)->toBeFalse();
});

it('keeps the last known roles while Discord is down (NF-2)', function () {
    $admin = staleAdmin(30);
    fakeGuildMember(['message' => 'Bad gateway'], 502);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    expect($admin->fresh()->is_admin)->toBeTrue();

    // A second request within the backoff minute does not hammer Discord.
    $this->actingAs($admin->fresh())->get(route('dashboard'))->assertOk();
    Http::assertSentCount(1);
});

it('refreshes the display name from the member payload', function () {
    $player = User::factory()->create(['discord_id' => '555000000000000558', 'display_name' => 'Old', 'roles_checked_at' => now()->subMinutes(11)]);
    fakeGuildMember(guildMemberPayload($player->discord_id, nick: 'New Nick'));

    $this->actingAs($player)->get(route('dashboard'));

    expect($player->fresh()->display_name)->toBe('New Nick');
});
