<?php

use App\Models\DiscordOutbox;
use App\Models\Submission;
use App\Models\User;
use App\Services\Discord\OutboxSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const CHANNEL = '300000000000000003';

function messagesUrl(): string
{
    return 'https://discord.com/api/v10/channels/'.CHANNEL.'/messages';
}

/** Approve a claim as an admin through the real endpoint; returns the claim. */
function approvedClaim(string $code = 'THERI_MELEE_250', string $player = 'Joe', string $discordId = '330915472668606464'): Submission
{
    $claim = Submission::factory()->for(User::factory()->named($player)->create(['discord_id' => $discordId]))->onMilestone($code)->create();
    test()->actingAs(admin('Kuba'.uniqid()))->post(route('admin.submissions.approve', $claim))->assertSessionHas('status');
    auth()->logout();

    return $claim->fresh();
}

function sendOutbox(): void
{
    test()->artisan('discord:send-outbox')->assertSuccessful();
}

it('announces a verified milestone with an embed and pings only the discoverer (DC-1, DC-2, DC-5)', function () {
    verifyMilestones(22);
    $claim = approvedClaim();
    Http::fake([messagesUrl() => Http::response(['id' => '111222333444555666'], 200)]);

    sendOutbox();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $embed = $request['embeds'][0];
        $fields = collect($embed['fields'])->pluck('value', 'name');

        return $request->method() === 'POST'
            && $request->url() === messagesUrl()
            && $request->hasHeader('Authorization', 'Bot test-bot-token')
            && $request['content'] === 'Gratulace, <@330915472668606464>! Nový ověřený milník.'
            && $embed['title'] === 'NOVÝ GENETICKÝ MILNÍK'
            && $embed['url'] === route('milestones.show', 'THERI_MELEE_250')
            && $fields->all() === [
                'Druh' => 'Therizinosaurus',
                'Stat' => 'Melee Damage',
                'Hodnota' => '250',
                'Objevitel' => 'Joe',
                'Progres' => '23 / 60',
                'Indo Potential' => '725 / 1650',
            ]
            && $request['allowed_mentions'] === ['parse' => [], 'users' => ['330915472668606464']]
            && str_starts_with($request['nonce'], 'gm-outbox-')
            && $request['enforce_nonce'] === true;
    });

    $row = DiscordOutbox::query()->sole();
    expect($row->status)->toBe('sent')
        ->and($row->message_id)->toBe('111222333444555666')
        ->and($row->sent_at)->not->toBeNull()
        ->and($claim->fresh()->discord_message_id)->toBe('111222333444555666');

    $this->get(route('milestones.show', 'THERI_MELEE_250'))->assertSee('oznámeno na Discordu');
});

it('sends the announcement after a Discord outage, exactly once (acceptance)', function () {
    $claim = approvedClaim();
    expect($claim->status->value)->toBe('verified'); // the decision did not wait for Discord

    Http::fake([messagesUrl() => Http::sequence()
        ->push(['message' => 'Service Unavailable'], 503)
        ->push(['id' => '999888777666555444'], 200)]);

    sendOutbox();
    $row = DiscordOutbox::query()->sole();
    expect($row->status)->toBe('pending')
        ->and($row->attempts)->toBe(1)
        ->and($row->last_error)->toContain('503')
        ->and($row->last_error)->not->toContain('test-bot-token')
        ->and($row->available_at->isFuture())->toBeTrue();

    // Still waiting: nothing is sent before the pause is over.
    sendOutbox();
    Http::assertSentCount(1);

    $this->travel(5)->minutes();
    sendOutbox();
    $this->travel(10)->minutes();
    sendOutbox();

    Http::assertSentCount(2);
    expect(DiscordOutbox::query()->sole())
        ->status->toBe('sent')
        ->message_id->toBe('999888777666555444')
        ->attempts->toBe(2);
});

it('backs off longer after every failure and honours retry_after on 429', function () {
    approvedClaim();
    Http::fake([messagesUrl() => Http::sequence()
        ->push(['message' => 'down'], 500)
        ->push(['message' => 'You are being rate limited.', 'retry_after' => 42.5], 429)]);

    sendOutbox();
    $first = DiscordOutbox::query()->sole()->available_at;
    expect(now()->diffInSeconds($first))->toBeGreaterThanOrEqual(29)->toBeLessThanOrEqual(31);

    $this->travel(31)->seconds();
    sendOutbox();
    $second = DiscordOutbox::query()->sole()->available_at;
    expect(now()->diffInSeconds($second))->toBeGreaterThanOrEqual(43)->toBeLessThanOrEqual(45);
});

it('gives up after too many attempts', function () {
    approvedClaim();
    DiscordOutbox::query()->update(['attempts' => OutboxSender::MAX_ATTEMPTS - 1]);
    Http::fake([messagesUrl() => Http::response(['message' => 'down'], 502)]);

    sendOutbox();

    expect(DiscordOutbox::query()->sole()->status)->toBe('failed');
});

it('never sends a row another runner has already claimed', function () {
    approvedClaim();
    DiscordOutbox::query()->update(['status' => 'sending', 'updated_at' => now()]);
    Http::fake();

    sendOutbox();

    Http::assertNothingSent();
    expect(DiscordOutbox::query()->sole()->status)->toBe('sending');
});

it('releases rows stuck in sending after a crash; the nonce prevents a duplicate on Discord', function () {
    approvedClaim();
    DiscordOutbox::query()->update(['status' => 'sending', 'updated_at' => now()->subMinutes(10)]);
    Http::fake([messagesUrl() => Http::response(['id' => '123'], 200)]);

    sendOutbox();

    Http::assertSent(fn (Request $r) => $r['nonce'] === 'gm-outbox-'.DiscordOutbox::query()->sole()->id);
    expect(DiscordOutbox::query()->sole()->status)->toBe('sent');
});

it('edits the original message and posts a correction after a revocation (DC-3)', function () {
    verifyMilestones(22);
    $claim = approvedClaim();
    Http::fake([
        messagesUrl() => Http::sequence()
            ->push(['id' => '555000000000000001'], 200)
            ->push(['id' => '555000000000000002'], 200),
        messagesUrl().'/555000000000000001' => Http::response(['id' => '555000000000000001'], 200),
    ]);
    sendOutbox();

    $this->actingAs(admin('Lenka'))->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => 'Upravený screenshot.'])->assertSessionHas('status');
    sendOutbox();

    Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
        && $r->url() === messagesUrl().'/555000000000000001'
        && $r['content'] === '**OVĚŘENÍ ZRUŠENO** ~~NOVÝ GENETICKÝ MILNÍK · Therizinosaurus · Melee Damage~~'
        && $r['embeds'] === []
        && $r['allowed_mentions'] === ['parse' => []]);

    Http::assertSent(fn (Request $r) => $r->method() === 'POST'
        && str_contains((string) $r['content'], '**Oprava:** Ověření milníku Therizinosaurus · Melee Damage bylo zrušeno. Progres je teď 22 / 60, Indo Potential 700 / 1650.')
        && $r['message_reference']['message_id'] === '555000000000000001'
        && $r['allowed_mentions'] === ['parse' => []]);

    expect(DiscordOutbox::query()->pluck('status')->unique()->all())->toBe(['sent']);
});

it('does not announce or correct a milestone revoked before the announcement went out', function () {
    $claim = approvedClaim();
    $this->actingAs(admin('Lenka'))->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => 'Omyl.']);
    Http::fake();

    sendOutbox();

    Http::assertNothingSent();
    expect(DiscordOutbox::query()->pluck('status')->all())->toBe(['cancelled']);
});

it('lets the edit wait while the announcement is still being sent', function () {
    $claim = approvedClaim();
    $announcement = DiscordOutbox::query()->sole();
    $announcement->forceFill(['status' => 'sending'])->save();
    $this->actingAs(admin('Lenka'))->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => 'Omyl.']);
    Http::fake();

    sendOutbox();

    Http::assertNothingSent();
    $edit = DiscordOutbox::query()->where('kind', DiscordOutbox::KIND_REVOKED_EDIT)->sole();
    expect($edit->status)->toBe('pending')
        ->and($edit->attempts)->toBe(0)
        ->and($edit->available_at->isFuture())->toBeTrue();
});
