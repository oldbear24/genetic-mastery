<?php

use App\Models\Milestone;
use App\Models\Submission;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $keys = sodium_crypto_sign_keypair();
    $this->secretKey = sodium_crypto_sign_secretkey($keys);
    config(['services.discord.public_key' => bin2hex(sodium_crypto_sign_publickey($keys))]);
});

/** POST a signed interaction like Discord does. */
function interaction(array $payload, ?string $secretKey = null, ?string $signature = null, ?string $timestamp = null): TestResponse
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $timestamp ??= (string) time();
    $signature ??= bin2hex(sodium_crypto_sign_detached($timestamp.$body, $secretKey ?? test()->secretKey));

    return test()->call('POST', '/discord/interactions', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE_ED25519' => $signature,
        'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
    ], $body);
}

function command(string $sub, array $options = []): array
{
    return [
        'type' => 2,
        'id' => '1',
        'token' => 'interaction-token',
        'data' => [
            'name' => 'genetic',
            'type' => 1,
            'options' => [['type' => 1, 'name' => $sub, 'options' => collect($options)->map(fn ($v, $k) => ['type' => 3, 'name' => $k, 'value' => $v])->values()->all()]],
        ],
    ];
}

it('answers PING with PONG', function () {
    interaction(['type' => 1])->assertOk()->assertExactJson(['type' => 1]);
});

it('refuses an invalid signature with 401', function () {
    $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

    interaction(['type' => 1], secretKey: $other)->assertStatus(401);
    interaction(['type' => 1], signature: str_repeat('0', 128))->assertStatus(401);
    interaction(['type' => 1], signature: 'not-hex')->assertStatus(401);

    // Signature valid for another body.
    $timestamp = (string) time();
    $signature = bin2hex(sodium_crypto_sign_detached($timestamp.'{"type":2}', $this->secretKey));
    interaction(['type' => 1], signature: $signature, timestamp: $timestamp)->assertStatus(401);
});

it('refuses requests without signature headers', function () {
    $this->postJson('/discord/interactions', ['type' => 1])->assertStatus(401);
});

it('does not need a CSRF token or start a session', function () {
    $response = interaction(['type' => 1])->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});

it('answers /genetic overview with V / total and 150 + 25 × V, within 1 s (acceptance)', function (int $verified, int $indo) {
    verifyMilestones($verified);

    $start = microtime(true);
    $response = interaction(command('overview'))->assertOk();
    $elapsed = microtime(true) - $start;

    $content = $response->json('data.content');
    expect($response->json('type'))->toBe(4)
        ->and($response->json('data.flags'))->toBeNull()
        ->and($response->json('data.allowed_mentions'))->toBe(['parse' => []])
        ->and($content)->toContain(sprintf('GENETIC MASTERY   %d / 60', $verified))
        ->and($content)->toContain(sprintf('INDO POTENTIAL    %d / 1650', $indo))
        ->and($content)->toContain('/genetic progress [druh]')
        ->and($elapsed)->toBeLessThan(1.0);
})->with([[0, 150], [23, 725], [60, 1650]]);

it('draws a text progress bar', function () {
    verifyMilestones(30);

    expect(interaction(command('overview'))->json('data.content'))->toContain(str_repeat('█', 10).str_repeat('░', 10).' 50 %');
});

it('answers /genetic progress per species, and the six stats of one species', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('REX_HEALTH_250')->verified()->create();
    Submission::factory()->onMilestone('REX_STAMINA_250')->create();

    expect(interaction(command('progress'))->json('data.content'))
        ->toContain('**Progres po druzích** (1 / 60)')
        ->toContain('Rex 1 / 6')
        ->toContain('Basilisk 0 / 6');

    expect(interaction(command('progress', ['druh' => 'REX']))->json('data.content'))
        ->toContain('**Rex** · 1 / 6 ověřeno')
        ->toContain('Health: OVĚŘENO – objevitel Joe')
        ->toContain('Stamina: čeká na schválení')
        ->toContain('Oxygen: zamčeno');

    expect(interaction(command('progress', ['druh' => 'DODO']))->json('data.flags'))->toBe(64);
});

it('answers /genetic leaderboard with the top 10', function () {
    $kira = player('Kira');
    Submission::factory()->for($kira)->onMilestone('REX_HEALTH_250')->verified()->create();
    Submission::factory()->for($kira)->onMilestone('REX_STAMINA_250')->verified()->create();
    Submission::factory()->for(player('Joe'))->onMilestone('REX_OXYGEN_250')->verified()->create();

    expect(interaction(command('leaderboard'))->json('data.content'))
        ->toContain('**Nejlepší objevitelé**')
        ->toContain('#1 Kira · 2 milníky')
        ->toContain('#2 Joe · 1 milník');
});

it('answers /genetic milestone only to the caller', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->verified()->create();

    $response = interaction(command('milestone', ['druh' => 'GIGA', 'stat' => 'MELEE']));

    expect($response->json('data.flags'))->toBe(64)
        ->and($response->json('data.content'))
        ->toContain('**Giganotosaurus · Melee Damage** (GIGA_MELEE_250) · OVĚŘENO – objevitel Joe')
        ->toContain(route('milestones.show', 'GIGA_MELEE_250'));

    expect(interaction(command('milestone', ['druh' => 'GIGA', 'stat' => 'LUCK']))->json('data.content'))->toBe('Takový milník neexistuje.');
});

it('never lets a player name ping anyone', function () {
    Submission::factory()->for(player('@everyone'))->onMilestone('GIGA_MELEE_250')->verified()->create();

    $data = interaction(command('leaderboard'))->json('data');

    expect($data['allowed_mentions'])->toBe(['parse' => []])
        ->and($data['content'])->not->toContain('@everyone');
});

it('registers /genetic as a guild command with Czech descriptions', function () {
    Http::fake(['discord.com/api/v10/applications/*' => Http::response([['name' => 'genetic']], 200)]);

    $this->artisan('discord:register-commands')->expectsOutputToContain('Registered: genetic')->assertSuccessful();

    Http::assertSent(function (Request $request) {
        $command = $request->data()[0];

        return $request->method() === 'PUT'
            && $request->url() === 'https://discord.com/api/v10/applications/100000000000000001/guilds/200000000000000002/commands'
            && $command['name'] === 'genetic'
            && collect($command['options'])->pluck('name')->all() === ['overview', 'progress', 'leaderboard', 'milestone']
            && $command['options'][0]['description'] === 'Progres clusteru a Indo Potential'
            && count($command['options'][3]['options'][0]['choices']) === 10
            && count($command['options'][3]['options'][1]['choices']) === 6;
    });
});

it('says that a switched off milestone is not used', function () {
    Milestone::query()->where('code', 'TUSO_OXYGEN_250')->update(['is_active' => false]);

    expect(interaction(command('milestone', ['druh' => 'TUSO', 'stat' => 'OXYGEN']))->json('data.content'))
        ->toBe('**Tusoteuthis · Oxygen** se v clusteru nepoužívá.');

    expect(interaction(command('progress', ['druh' => 'TUSO']))->json('data.content'))
        ->toContain('**Tusoteuthis** · 0 / 5 ověřeno')
        ->not->toContain('Oxygen');
});
