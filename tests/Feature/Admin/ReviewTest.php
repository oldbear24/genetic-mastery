<?php

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\DiscordOutbox;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Models\SubmissionNote;
use App\Models\User;
use App\Services\ClusterProgress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function pendingClaim(string $code = 'REX_MELEE_250', ?User $player = null, array $attributes = []): Submission
{
    return Submission::factory()->for($player ?? player('P'.uniqid()))->onMilestone($code)->create($attributes);
}

describe('access', function () {
    it('gives players 403 on every admin address (SEC-2)', function () {
        $claim = pendingClaim();
        $player = player('Joe');

        $this->actingAs($player)->get(route('admin.queue'))->assertForbidden();
        $this->actingAs($player)->get(route('admin.submissions.show', $claim))->assertForbidden();
        $this->actingAs($player)->post(route('admin.submissions.approve', $claim))->assertForbidden();
        $this->actingAs($player)->post(route('admin.submissions.reject', $claim), ['reason' => 'x'])->assertForbidden();

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Pending);
    });

    it('sends guests to the login page', function () {
        $claim = pendingClaim();

        $this->get(route('admin.queue'))->assertRedirect(route('login'));
        $this->post(route('admin.submissions.approve', $claim))->assertRedirect(route('login'));
    });

    it('takes admin rights away within 10 minutes after the role is removed', function () {
        $admin = admin('Kuba');
        $admin->forceFill(['roles_checked_at' => now()->subMinutes(11)])->save();
        fakeGuildMember(guildMemberPayload($admin->discord_id, roles: []));

        $this->actingAs($admin)->get(route('admin.queue'))->assertForbidden();
    });
});

describe('queue and detail', function () {
    it('lists pending claims oldest first and filters by species, stat and player (ADM-1)', function () {
        $old = pendingClaim('ALLO_OXYGEN_250', player('Ondra'), ['submitted_at' => now()->subDays(2)]);
        $mid = pendingClaim('YUTY_MELEE_250', player('Eva'), ['submitted_at' => now()->subDay()]);
        $new = pendingClaim('REX_MELEE_250', player('Kira'), ['submitted_at' => now()->subHour()]);
        Submission::factory()->onMilestone('GIGA_HEALTH_250')->verified()->create();

        $this->actingAs(admin())->get(route('admin.queue'))
            ->assertOk()
            ->assertSeeInOrder([$old->public_id, $mid->public_id, $new->public_id])
            ->assertSee('Žádosti ke schválení');

        $this->actingAs(admin('A2'))->get(route('admin.queue', ['q' => 'yuty']))
            ->assertSee($mid->public_id)->assertDontSee($old->public_id)->assertDontSee($new->public_id);
        $this->actingAs(admin('A3'))->get(route('admin.queue', ['q' => 'melee']))
            ->assertSee($mid->public_id)->assertSee($new->public_id)->assertDontSee($old->public_id);
        $this->actingAs(admin('A4'))->get(route('admin.queue', ['q' => 'ondra']))
            ->assertSee($old->public_id)->assertDontSee($mid->public_id);
        $this->actingAs(admin('A5'))->get(route('admin.queue', ['q' => 'rex melee']))
            ->assertSee($new->public_id)->assertDontSee($mid->public_id);
    });

    it('shows the player, milestone, evidence, date and note (ADM-2)', function () {
        $kira = player('Kira');
        $claim = pendingClaim('REX_MELEE_250', $kira, ['player_note' => '252 bodů po poslední mutaci.']);
        SubmissionImage::factory()->for($claim)->create(['original_name' => 'stats.png']);

        $this->actingAs(admin())->get(route('admin.submissions.show', $claim))
            ->assertOk()
            ->assertSee('REX_MELEE_250 · požadovaná hodnota 250')
            ->assertSee('Rex · Melee Damage')
            ->assertSee('Kira')
            ->assertSee("Discord ID {$kira->discord_id}")
            ->assertSee('252 bodů po poslední mutaci.')
            ->assertSee('stats.png')
            ->assertSee('Originál')
            ->assertSee("Žádost {$claim->public_id} · odesláno")
            ->assertSee('0 ověřeno · 0 zamítnuto');
    });

    it('warns about parallel claims and asks to confirm a later one (ADM-3)', function () {
        $earlier = pendingClaim('REX_MELEE_250', player('Kira'), ['submitted_at' => now()->subHours(4)]);
        $later = pendingClaim('REX_MELEE_250', player('Joe'), ['submitted_at' => now()->subHour()]);

        $this->actingAs(admin())->get(route('admin.submissions.show', $earlier))
            ->assertSee('SOUBĚŽNÉ')
            ->assertSee("Další čekající žádost na tento milník: {$later->public_id} od hráče Joe, odeslaná později")
            ->assertDontSee('POZDĚJŠÍ ŽÁDOST')
            ->assertSee('Schválit žádost');

        $this->actingAs(admin('A2'))->get(route('admin.submissions.show', $later))
            ->assertSee('odeslaná dřív')
            ->assertSee('POZDĚJŠÍ ŽÁDOST')
            ->assertSee("Existuje dřívější žádost: {$earlier->public_id} od hráče Kira")
            ->assertSee('Schválit i tak')
            ->assertSee('name="confirm_later"', false);
    });

    it('warns when the same image appears in another submission (ADM-4)', function () {
        $first = pendingClaim('REX_MELEE_250', player('Kira'));
        $second = pendingClaim('REX_MELEE_250', player('Joe'));
        $hash = str_repeat('ab', 32);
        SubmissionImage::factory()->for($first)->create(['sha256' => $hash]);
        SubmissionImage::factory()->for($second)->create(['sha256' => $hash, 'original_name' => 'ancestry-mutations.png']);
        SubmissionImage::factory()->for($second)->create(['sha256' => str_repeat('cd', 32), 'position' => 2]);

        $this->actingAs(admin())->get(route('admin.submissions.show', $second))
            ->assertSee('DUPLICITA')
            ->assertSee("Soubor ancestry-mutations.png má stejný SHA-256 jako obrázek v žádosti {$first->public_id} (Kira).")
            ->assertDontSee('V jiných žádostech se žádný stejný obrázek nenašel.');

        $third = pendingClaim('GIGA_MELEE_250');
        SubmissionImage::factory()->for($third)->create(['sha256' => str_repeat('ef', 32)]);

        $this->actingAs(admin('A2'))->get(route('admin.submissions.show', $third))
            ->assertSee('V jiných žádostech se žádný stejný obrázek nenašel.');
    });
});

describe('approve', function () {
    it('verifies, closes other claims, writes the audit and queues Discord in one go (ADM-5)', function () {
        Http::fake(); // nothing may be sent to Discord from the request
        $kuba = admin('Kuba');
        $claim = pendingClaim('REX_MELEE_250', player('Kira'), ['submitted_at' => now()->subHours(3)]);
        $other = pendingClaim('REX_MELEE_250', player('Joe'), ['submitted_at' => now()->subHour()]);
        $unrelated = pendingClaim('GIGA_MELEE_250');
        $before = app(ClusterProgress::class)->verifiedCount();

        $this->actingAs($kuba)
            ->post(route('admin.submissions.approve', $claim), ['public_note' => 'Ověřeno podle záznamu mutací.'])
            ->assertRedirect(route('admin.queue'))
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'Schváleno · REX_MELEE_250 je ověřený. Progres clusteru je teď 1 / 54 a Indo Potential 175 / 1500. Objevitel: Kira.')
                && str_contains($message, 'Jedna další žádost byla uzavřena.'));

        $claim->refresh();
        expect($claim->status)->toBe(SubmissionStatus::Verified)
            ->and($claim->reviewed_by)->toBe($kuba->id)
            ->and($claim->reviewed_at)->not->toBeNull()
            ->and($other->fresh()->status)->toBe(SubmissionStatus::Superseded)
            ->and($other->fresh()->superseded_by_id)->toBe($claim->id)
            ->and($unrelated->fresh()->status)->toBe(SubmissionStatus::Pending)
            ->and(app(ClusterProgress::class)->verifiedCount())->toBe($before + 1);

        expect(SubmissionNote::query()->where('submission_id', $claim->id)->first())
            ->visibility->toBe('public')
            ->body->toBe('Ověřeno podle záznamu mutací.')
            ->author_id->toBe($kuba->id);

        expect(AuditLog::query()->orderBy('id')->pluck('action')->all())->toBe(['submission.approved', 'submission.superseded', 'note.added'])
            ->and(AuditLog::query()->where('action', 'submission.approved')->first())
            ->actor_id->toBe($kuba->id)
            ->entity_id->toBe($claim->id)
            ->before->toBe(['status' => 'pending'])
            ->after->toBe(['status' => 'verified', 'reviewed_by' => $kuba->id]);

        $outbox = DiscordOutbox::query()->sole();
        expect($outbox->kind)->toBe(DiscordOutbox::KIND_VERIFIED)
            ->and($outbox->status)->toBe('pending')
            ->and($outbox->submission_id)->toBe($claim->id)
            ->and($outbox->payload)->toMatchArray(['verified' => 1, 'total' => 54, 'indo' => 175, 'indo_max' => 1500]);

        Http::assertNothingSent();
    });

    it('requires confirmation to approve a later claim (ADM-3)', function () {
        pendingClaim('REX_MELEE_250', player('Kira'), ['submitted_at' => now()->subHours(3)]);
        $later = pendingClaim('REX_MELEE_250', player('Joe'), ['submitted_at' => now()->subHour()]);

        $this->actingAs(admin())->post(route('admin.submissions.approve', $later))
            ->assertSessionHas('error', 'Na tento milník čeká dřívější žádost. Schválení pozdější žádosti je potřeba potvrdit.');
        expect($later->fresh()->status)->toBe(SubmissionStatus::Pending);

        $this->actingAs(admin('A2'))->post(route('admin.submissions.approve', $later), ['confirm_later' => '1'])
            ->assertSessionHas('status');
        expect($later->fresh()->status)->toBe(SubmissionStatus::Verified);
    });

    it('refuses the second approval of the same milestone; progress goes up by one', function () {
        $first = pendingClaim('REX_MELEE_250', player('Kira'), ['submitted_at' => now()->subHours(3)]);
        $second = pendingClaim('REX_MELEE_250', player('Joe'), ['submitted_at' => now()->subHour()]);

        // Admin B opened the second claim before admin A approved the first.
        $this->actingAs(admin('Kuba'))->post(route('admin.submissions.approve', $first))->assertSessionHas('status');
        $this->actingAs(admin('Lenka'))->post(route('admin.submissions.approve', $second), ['confirm_later' => '1'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'už nečeká na schválení'));

        expect(Submission::query()->verified()->count())->toBe(1)
            ->and(app(ClusterProgress::class)->verifiedCount())->toBe(1)
            ->and($second->fresh()->status)->toBe(SubmissionStatus::Superseded);
    });

    it('refuses to verify a milestone that another transaction already verified', function () {
        // The state a concurrent approval can leave between our read and write:
        // the milestone is verified while this claim still looks pending.
        Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();
        $claim = pendingClaim('REX_MELEE_250');

        $this->actingAs(admin())->post(route('admin.submissions.approve', $claim))
            ->assertSessionHas('error', 'Tento milník je mezitím už ověřený jinou žádostí. Tuto žádost nejde schválit.');

        expect(Submission::query()->verified()->count())->toBe(1)
            ->and($claim->fresh()->status)->toBe(SubmissionStatus::Pending)
            ->and(DiscordOutbox::count())->toBe(0)
            ->and(AuditLog::count())->toBe(0);
    });

    it('does not let an admin approve their own claim when ALLOW_SELF_APPROVE=false (ADM-7)', function () {
        config(['genetic.allow_self_approve' => false]);
        $kuba = admin('Kuba');
        $claim = pendingClaim('REX_MELEE_250', $kuba);

        $this->actingAs($kuba)->get(route('admin.submissions.show', $claim))
            ->assertSee('Tohle je vaše vlastní žádost. Rozhodnout o ní musí jiný admin.')
            ->assertDontSee('Schválit žádost');
        $this->actingAs($kuba)->post(route('admin.submissions.approve', $claim))->assertForbidden();
        $this->actingAs($kuba)->post(route('admin.submissions.reject', $claim), ['reason' => 'test'])->assertForbidden();

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Pending);
    });

    it('lets an admin approve their own claim when ALLOW_SELF_APPROVE=true', function () {
        config(['genetic.allow_self_approve' => true]);
        $kuba = admin('Kuba');
        $claim = pendingClaim('REX_MELEE_250', $kuba);

        $this->actingAs($kuba)->post(route('admin.submissions.approve', $claim))->assertSessionHas('status');

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Verified);
    });
});

describe('reject', function () {
    it('requires a reason the player then sees (ADM-6)', function () {
        $joe = player('Joe');
        $claim = pendingClaim('THERI_FOOD_250', $joe);

        $this->actingAs(admin())->post(route('admin.submissions.reject', $claim), ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Napište důvod zamítnutí. Hráč ho uvidí.']);
        expect($claim->fresh()->status)->toBe(SubmissionStatus::Pending);

        $kuba = admin('Kuba');
        $this->actingAs($kuba)->post(route('admin.submissions.reject', $claim), ['reason' => 'Chybí screenshot předků.'])
            ->assertRedirect(route('admin.queue'))
            ->assertSessionHas('status', "Zamítnuto · {$claim->public_id}. Hráč Joe uvidí důvod ve svém přehledu a může poslat novou žádost.");

        $claim->refresh();
        expect($claim->status)->toBe(SubmissionStatus::Rejected)
            ->and($claim->rejection_reason)->toBe('Chybí screenshot předků.')
            ->and($claim->reviewed_by)->toBe($kuba->id)
            ->and(AuditLog::query()->sole())->action->toBe('submission.rejected')->reason->toBe('Chybí screenshot předků.')
            ->and(DiscordOutbox::count())->toBe(0);

        $this->actingAs($joe)->get(route('dashboard'))->assertSee('Důvod: Chybí screenshot předků.');
    });

    it('does not reject a claim that is no longer pending', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();

        $this->actingAs(admin())->post(route('admin.submissions.reject', $claim), ['reason' => 'Pozdě.'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'už nečeká na schválení'));

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Verified);
    });
});

describe('audit log', function () {
    it('cannot be changed or deleted (SEC-6)', function () {
        $claim = pendingClaim();
        $this->actingAs(admin())->post(route('admin.submissions.reject', $claim), ['reason' => 'Chybí předci.']);
        $entry = AuditLog::query()->sole();

        expect(fn () => $entry->update(['reason' => 'changed']))->toThrow(LogicException::class)
            ->and(fn () => $entry->delete())->toThrow(LogicException::class)
            ->and(fn () => DB::table('audit_log')->update(['reason' => 'changed']))->toThrow(QueryException::class)
            ->and(fn () => DB::table('audit_log')->delete())->toThrow(QueryException::class);

        expect(AuditLog::query()->sole()->reason)->toBe('Chybí předci.');
    });
});
