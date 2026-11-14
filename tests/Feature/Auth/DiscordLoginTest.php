<?php

use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

const DISCORD_ID = '184772930117345280';
const OAUTH_TOKEN = 'oauth-access-token-must-not-be-stored';

function fakeOAuthUser(string $id = DISCORD_ID, string $username = 'joe', ?string $globalName = 'Joe', ?string $avatar = 'abc123'): void
{
    $user = (new SocialiteUser)
        ->setRaw(['id' => $id, 'username' => $username, 'global_name' => $globalName, 'avatar' => $avatar, 'discriminator' => '0'])
        ->map(['id' => $id, 'nickname' => $username, 'name' => $globalName]);
    $user->token = OAUTH_TOKEN;
    $user->refreshToken = 'refresh-token-must-not-be-stored';

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);
}

it('redirects to Discord with the identify scope and a state parameter (AUTH-1)', function () {
    $response = $this->get(route('auth.discord'));

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($location)->toStartWith('https://discord.com/')
        ->and($query['scope'])->toBe('identify')
        ->and($query['response_type'])->toBe('code')
        ->and($query['client_id'])->toBe(config('services.discord.client_id'))
        ->and($query['state'])->toBeString()->not->toBeEmpty();
});

it('signs in a member, stores Discord identity and discards the OAuth token (AUTH-2, AUTH-7)', function () {
    fakeOAuthUser();
    fakeGuildMember(guildMemberPayload(DISCORD_ID, roles: ['123'], nick: 'JoeBreeds'));

    $this->get(route('auth.discord.callback', ['code' => 'abc', 'state' => 'xyz']))
        ->assertRedirect(route('dashboard'));

    $user = User::where('discord_id', DISCORD_ID)->firstOrFail();
    $this->assertAuthenticatedAs($user);

    expect($user->username)->toBe('joe')
        ->and($user->global_name)->toBe('Joe')
        ->and($user->display_name)->toBe('JoeBreeds')
        ->and($user->avatar_hash)->toBe('abc123')
        ->and($user->is_admin)->toBeFalse()
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->roles_checked_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/v10/guilds/'.config('services.discord.guild_id').'/members/'.DISCORD_ID
        && $request->hasHeader('Authorization', 'Bot test-bot-token'));

    // Neither the access token nor the refresh token is stored anywhere.
    $dump = json_encode(DB::table('users')->get()).json_encode(session()->all());
    expect($dump)->not->toContain(OAUTH_TOKEN)->not->toContain('refresh-token-must-not-be-stored');
});

it('makes users with an admin role admins (AUTH-3)', function () {
    fakeOAuthUser();
    fakeGuildMember(guildMemberPayload(DISCORD_ID, roles: ['111', '900000000000000009']));

    $this->get(route('auth.discord.callback', ['code' => 'abc']));

    expect(User::where('discord_id', DISCORD_ID)->first()->isAdmin())->toBeTrue();
});

it('makes users listed in ADMIN_USER_IDS admins even without the role (AUTH-3)', function () {
    config(['genetic.admin_user_ids' => [DISCORD_ID]]);
    fakeOAuthUser();
    fakeGuildMember(guildMemberPayload(DISCORD_ID, roles: []));

    $this->get(route('auth.discord.callback', ['code' => 'abc']));

    expect(User::where('discord_id', DISCORD_ID)->first()->isAdmin())->toBeTrue();
});

it('refuses users who are not members of the cluster server (AUTH-2)', function () {
    fakeOAuthUser();
    fakeGuildMember(null);

    $this->get(route('auth.discord.callback', ['code' => 'abc']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Přihlásit se můžou jen členové Discord serveru clusteru.');

    $this->assertGuest();
    expect(User::where('discord_id', DISCORD_ID)->exists())->toBeFalse();
});

it('refuses login when Discord cannot confirm membership', function () {
    fakeOAuthUser();
    fakeGuildMember(['message' => 'Service unavailable'], 503);

    $this->get(route('auth.discord.callback', ['code' => 'abc']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', __('auth.discord.unavailable'));

    $this->assertGuest();
});

it('handles a cancelled authorization', function () {
    $this->get(route('auth.discord.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', __('auth.discord.cancelled'));
});

it('rejects an invalid state', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('discord')->andReturn($provider);

    $this->get(route('auth.discord.callback', ['code' => 'abc', 'state' => 'forged']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', __('auth.discord.invalid_state'));

    $this->assertGuest();
});

it('shows the server nickname, then global_name, then username (AUTH-5)', function (?string $nick, ?string $global, string $expected) {
    fakeOAuthUser(globalName: $global);
    fakeGuildMember(guildMemberPayload(DISCORD_ID, nick: $nick, globalName: $global));

    $this->get(route('auth.discord.callback', ['code' => 'abc']));

    expect(User::where('discord_id', DISCORD_ID)->first()->display_name)->toBe($expected);
})->with([
    'nickname' => ['JoeBreeds', 'Joe', 'JoeBreeds'],
    'global name' => [null, 'Joe', 'Joe'],
    'username' => [null, null, 'joe'],
]);

it('keeps history when a player renames on Discord and logs in again', function () {
    $joe = User::factory()->create(['discord_id' => DISCORD_ID, 'username' => 'joe', 'display_name' => 'JoeBreeds', 'guild_nickname' => 'JoeBreeds']);
    $submission = Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->verified()->create(['player_name_snapshot' => 'JoeBreeds']);

    fakeOAuthUser(username: 'joe');
    fakeGuildMember(guildMemberPayload(DISCORD_ID, nick: 'Joe the Breeder'));

    $this->get(route('auth.discord.callback', ['code' => 'abc']));

    expect(User::where('discord_id', DISCORD_ID)->count())->toBe(1)
        ->and($joe->fresh()->display_name)->toBe('Joe the Breeder')
        ->and($submission->fresh()->user_id)->toBe($joe->id)
        ->and($submission->fresh()->player_name_snapshot)->toBe('JoeBreeds');

    $this->get(route('milestones.show', 'GIGA_MELEE_250'))
        ->assertSee('OVĚŘENO – objevitel Joe the Breeder')
        ->assertSee('odesláno jako „JoeBreeds“', false);
});

it('signs out', function () {
    $this->actingAs(player())->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

it('rate limits the login endpoints', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->get(route('auth.discord'))->assertRedirect();
    }

    $this->get(route('auth.discord'))->assertStatus(429);
});

it('shows a login page with the Discord button', function () {
    $this->get(route('login'))->assertOk()->assertSee('Přihlásit přes Discord')->assertSee(route('auth.discord'), false);
});

it('sends guests from player pages to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('signs in a user loaded from the database (no password column)', function () {
    $id = User::factory()->create()->id;

    Auth::login(User::query()->findOrFail($id));

    $this->assertAuthenticated();
    expect(User::query()->findOrFail($id)->getAuthPassword())->toBe('');
});
