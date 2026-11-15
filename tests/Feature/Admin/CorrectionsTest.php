<?php

use App\Enums\SubmissionStatus;
use App\Models\AuditLog;
use App\Models\DiscordOutbox;
use App\Models\Submission;
use App\Models\SubmissionNote;
use App\Models\User;
use App\Services\ClusterProgress;

/** Approve through the real endpoint, so outbox and audit rows exist like in production. */
function approveViaAdmin(Submission $claim, ?User $admin = null, bool $confirm = true): void
{
    test()->actingAs($admin ?? admin('Approver'.uniqid()))
        ->post(route('admin.submissions.approve', $claim), $confirm ? ['confirm_later' => '1'] : [])
        ->assertSessionHas('status');
}

describe('revoke verification (OPR-2)', function () {
    it('lowers progress by 1 and Indo by 25, reopens superseded claims, queues the Discord edit and writes the audit', function () {
        verifyMilestones(10);
        $winner = Submission::factory()->for(player('Kira'))->onMilestone('BASILISK_MELEE_250')->create(['submitted_at' => now()->subHours(3)]);
        $loser = Submission::factory()->for(player('Joe'))->onMilestone('BASILISK_MELEE_250')->create(['submitted_at' => now()->subHour()]);
        approveViaAdmin($winner);
        // The announcement went out in the meantime (stage 6 sends it).
        DiscordOutbox::query()->update(['status' => DiscordOutbox::STATUS_SENT, 'message_id' => '999']);

        $progress = app(ClusterProgress::class);
        expect($progress->verifiedCount())->toBe(11)->and($progress->indoPotential())->toBe(425)
            ->and($loser->fresh()->status)->toBe(SubmissionStatus::Superseded);

        $lenka = admin('Lenka');
        $this->actingAs($lenka)->post(route('admin.submissions.revoke', $winner), ['revoke_reason' => 'Screenshot je upravený.'])
            ->assertSessionHas('status', fn ($m) => str_contains($m, "Ověření žádosti {$winner->public_id} je zrušené. Progres clusteru je teď 10 / 60 a Indo Potential 400.")
                && str_contains($m, 'Jedna uzavřená žádost se vrátila ke schválení.'));

        $winner->refresh();
        expect($winner->status)->toBe(SubmissionStatus::Revoked)
            ->and($winner->revoked_by)->toBe($lenka->id)
            ->and($winner->revoke_reason)->toBe('Screenshot je upravený.')
            ->and($loser->fresh()->status)->toBe(SubmissionStatus::Pending)
            ->and($loser->fresh()->superseded_by_id)->toBeNull()
            ->and($progress->verifiedCount())->toBe(10)
            ->and($progress->indoPotential())->toBe(400);

        $revokeAudit = AuditLog::query()->where('action', 'submission.revoked')->sole();
        expect($revokeAudit->actor_id)->toBe($lenka->id)
            ->and($revokeAudit->before['status'])->toBe('verified')
            ->and($revokeAudit->after['status'])->toBe('revoked')
            ->and($revokeAudit->reason)->toBe('Screenshot je upravený.')
            ->and(AuditLog::query()->where('action', 'submission.reopened_after_revoke')->where('entity_id', $loser->id)->exists())->toBeTrue();

        $kinds = DiscordOutbox::query()->where('status', 'pending')->orderBy('id')->pluck('kind')->all();
        expect($kinds)->toBe([DiscordOutbox::KIND_REVOKED_EDIT, DiscordOutbox::KIND_REVOKED_CORRECTION])
            ->and(DiscordOutbox::query()->where('kind', DiscordOutbox::KIND_REVOKED_CORRECTION)->first()->payload)
            ->toMatchArray(['verified' => 10, 'indo' => 400, 'total' => 60, 'indo_max' => 1650]);
    });

    it('cancels an announcement that has not been sent yet instead of editing it', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create();
        approveViaAdmin($claim);

        $this->actingAs(admin('Lenka'))->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => 'Omyl.']);

        expect(DiscordOutbox::query()->pluck('status', 'kind')->all())->toBe([DiscordOutbox::KIND_VERIFIED => 'cancelled']);
    });

    it('requires a reason and a verified submission', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();

        $this->actingAs(admin())->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => ''])
            ->assertSessionHasErrors(['revoke_reason' => 'Napište důvod zrušení ověření.']);

        $pending = Submission::factory()->onMilestone('GIGA_MELEE_250')->create();
        $this->actingAs(admin('A2'))->post(route('admin.submissions.revoke', $pending), ['revoke_reason' => 'x x x'])
            ->assertSessionHas('error', "Žádost {$pending->public_id} není ověřená.");

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Verified);
    });

    it('is for admins only and not for their own claims', function () {
        $joe = player('Joe');
        $claim = Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->verified()->create();

        $this->actingAs($joe)->post(route('admin.submissions.revoke', $claim), ['revoke_reason' => 'Chci zpět.'])->assertForbidden();

        $kuba = admin('Kuba');
        $own = Submission::factory()->for($kuba)->onMilestone('GIGA_MELEE_250')->verified()->create();
        $this->actingAs($kuba)->post(route('admin.submissions.revoke', $own), ['revoke_reason' => 'Vlastní.'])->assertForbidden();

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Verified)
            ->and($own->fresh()->status)->toBe(SubmissionStatus::Verified);
    });
});

describe('reopen (OPR-3)', function () {
    it('puts a rejected claim back to pending and clears the old decision', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->rejected('Chybí předci.')->create();

        $this->actingAs(admin('Kuba'))->post(route('admin.submissions.reopen', $claim))
            ->assertSessionHas('status', "Žádost {$claim->public_id} znovu čeká na schválení.");

        $claim->refresh();
        expect($claim->status)->toBe(SubmissionStatus::Pending)
            ->and($claim->rejection_reason)->toBeNull()
            ->and($claim->reviewed_by)->toBeNull();

        $audit = AuditLog::query()->where('action', 'submission.reopened')->sole();
        expect($audit->before)->toMatchArray(['status' => 'rejected', 'rejection_reason' => 'Chybí předci.'])
            ->and($audit->after)->toBe(['status' => 'pending']);
    });

    it('puts a revoked claim back to pending', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create(['status' => SubmissionStatus::Revoked, 'revoke_reason' => 'Omyl', 'revoked_by' => admin('X')->id, 'revoked_at' => now()]);

        $this->actingAs(admin('Kuba'))->post(route('admin.submissions.reopen', $claim))->assertSessionHas('status');

        expect($claim->fresh()->status)->toBe(SubmissionStatus::Pending)
            ->and($claim->fresh()->revoke_reason)->toBeNull();
    });

    it('refuses other states, verified milestones and a second pending claim of the player', function () {
        $pending = Submission::factory()->onMilestone('REX_HEALTH_250')->create();
        $this->actingAs(admin())->post(route('admin.submissions.reopen', $pending))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'nejde znovu otevřít'));

        Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();
        $rejected = Submission::factory()->onMilestone('REX_MELEE_250')->rejected()->create();
        $this->actingAs(admin('A2'))->post(route('admin.submissions.reopen', $rejected))
            ->assertSessionHas('error', 'Milník už je ověřený jinou žádostí, takže tuto nejde znovu otevřít.');

        $joe = player('Joe');
        $old = Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->rejected()->create();
        Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->create();
        $this->actingAs(admin('A3'))->post(route('admin.submissions.reopen', $old))
            ->assertSessionHas('error', 'Hráč už má u tohoto milníku jinou čekající žádost.');

        expect($rejected->fresh()->status)->toBe(SubmissionStatus::Rejected)
            ->and($old->fresh()->status)->toBe(SubmissionStatus::Rejected);
    });
});

describe('change verifier (OPR-4)', function () {
    it('changes the verifying admin and writes the audit', function () {
        $kuba = admin('Kuba');
        $lenka = admin('Lenka');
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->verified($kuba)->create();

        $this->actingAs(admin('Boss'))->post(route('admin.submissions.verifier', $claim), ['reviewer_id' => $lenka->id])
            ->assertSessionHas('status', "Ověřující admin žádosti {$claim->public_id} je teď Lenka.");

        expect($claim->fresh()->reviewed_by)->toBe($lenka->id)
            ->and(AuditLog::query()->sole())
            ->action->toBe('submission.verifier_changed')
            ->before->toBe(['reviewed_by' => $kuba->id])
            ->after->toBe(['reviewed_by' => $lenka->id]);

        $this->get(route('milestones.show', 'REX_MELEE_250'))->assertSee('Ověřeno · admin Lenka');
    });

    it('accepts only admins as verifier', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();

        $this->actingAs(admin())->post(route('admin.submissions.verifier', $claim), ['reviewer_id' => player('Joe')->id])
            ->assertSessionHasErrors('reviewer_id');
    });
});

describe('notes (OPR-5)', function () {
    it('adds public notes for everyone and internal notes for admins only, as plain text', function () {
        $claim = Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->verified(admin('Kuba'))->create();

        $this->actingAs(admin('Kuba2'))->post(route('admin.submissions.notes', $claim), ['visibility' => 'public', 'body' => 'Ověřeno podle záznamu mutací. <b>tučně</b>'])
            ->assertSessionHas('status', 'Poznámka je uložená.');
        $this->actingAs(admin('Kuba3'))->post(route('admin.submissions.notes', $claim), ['visibility' => 'internal', 'body' => 'Hráč je známý podvodník?']);

        expect(SubmissionNote::count())->toBe(2)
            ->and(AuditLog::query()->where('action', 'note.added')->count())->toBe(2);

        auth()->logout();
        $this->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertSee('Ověřeno podle záznamu mutací. &lt;b&gt;tučně&lt;/b&gt;', false)
            ->assertDontSee('<b>tučně</b>', false)
            ->assertDontSee('Hráč je známý podvodník?');

        $this->actingAs(admin('Viewer'))->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertSee('Hráč je známý podvodník?');
    });

    it('validates visibility and length', function () {
        $claim = Submission::factory()->onMilestone('GIGA_MELEE_250')->create();

        $this->actingAs(admin())->post(route('admin.submissions.notes', $claim), ['visibility' => 'secret', 'body' => str_repeat('x', 1001)])
            ->assertSessionHasErrors(['visibility', 'body']);
    });
});

describe('lists (OPR-1, OPR-7)', function () {
    it('lists all submissions with filters by status, milestone, player and admin', function () {
        $kuba = admin('Kuba');
        $lenka = admin('Lenka');
        $verified = Submission::factory()->for(player('Kira'))->onMilestone('REX_MELEE_250')->verified($kuba)->create();
        $rejected = Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->rejected('x', $lenka)->create();
        $pending = Submission::factory()->for(player('Eva'))->onMilestone('GIGA_HEALTH_250')->create();

        $viewer = admin('Viewer');
        $this->actingAs($viewer)->get(route('admin.submissions'))
            ->assertOk()->assertSee($verified->public_id)->assertSee($rejected->public_id)->assertSee($pending->public_id);
        $this->actingAs($viewer)->get(route('admin.submissions', ['status' => 'rejected']))
            ->assertSee($rejected->public_id)->assertDontSee($verified->public_id)->assertDontSee($pending->public_id);
        $this->actingAs($viewer)->get(route('admin.submissions', ['milestone' => 'REX_MELEE_250']))
            ->assertSee($verified->public_id)->assertDontSee($rejected->public_id);
        $this->actingAs($viewer)->get(route('admin.submissions', ['player' => 'eva']))
            ->assertSee($pending->public_id)->assertDontSee($verified->public_id);
        $this->actingAs($viewer)->get(route('admin.submissions', ['admin' => $kuba->id]))
            ->assertSee($verified->public_id)->assertDontSee($rejected->public_id)->assertDontSee($pending->public_id);
    });

    it('shows the audit log with old and new values and a filter', function () {
        $kuba = admin('Kuba');
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create();
        $other = Submission::factory()->onMilestone('GIGA_MELEE_250')->create();
        $this->actingAs($kuba)->post(route('admin.submissions.reject', $claim), ['reason' => 'Chybí předci.']);
        approveViaAdmin($other, admin('Lenka'));

        $this->actingAs($kuba)->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Historie změn')
            ->assertSee('Zamítnutí')
            ->assertSee('Schválení')
            ->assertSee('Chybí předci.')
            ->assertSeeInOrder(['stav:', 'Čeká na schválení', 'Zamítnuto']);

        $this->actingAs($kuba)->get(route('admin.audit', ['action' => 'submission.approved']))
            ->assertSee($other->public_id)->assertDontSee('Chybí předci.');
        $this->actingAs($kuba)->get(route('admin.audit', ['submission' => strtolower($claim->public_id)]))
            ->assertSee('Chybí předci.')->assertDontSee($other->public_id);
        $this->actingAs($kuba)->get(route('admin.audit', ['actor' => $kuba->id]))
            ->assertSee('Zamítnutí')->assertDontSee($other->public_id);
    });

    it('is not available to players', function () {
        $player = player();

        $this->actingAs($player)->get(route('admin.submissions'))->assertForbidden();
        $this->actingAs($player)->get(route('admin.audit'))->assertForbidden();
    });
});

describe('milestone history (HIS-1 to HIS-3)', function () {
    it('shows discoverer, dates, verifying admin, evidence and public notes to everyone', function () {
        $joe = player('Joe');
        $claim = Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->create(['submitted_at' => now()->subMinutes(40)]);
        approveViaAdmin($claim, admin('Kuba'));
        $this->actingAs(admin('Kuba2'))->post(route('admin.submissions.notes', $claim), ['visibility' => 'public', 'body' => 'Zkontrolováno.']);
        auth()->logout();

        $this->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertOk()
            ->assertSee('OVĚŘENO – objevitel Joe')
            ->assertSee('Objevitel')
            ->assertSee('Ověřující admin')
            ->assertSee('Kuba')
            ->assertSee("žádost {$claim->public_id}")
            ->assertSee('Historie')
            ->assertSee('Ověřeno · admin Kuba')
            ->assertSee("Odeslaná žádost {$claim->public_id} · Joe")
            ->assertSee('Poznámka admina')
            ->assertSee('Zkontrolováno.')
            ->assertDontSee('Dřívější pokusy');
    });

    it('shows earlier attempts with reasons to admins only (HIS-2)', function () {
        $rejected = Submission::factory()->for(player('Ondra'))->onMilestone('GIGA_MELEE_250')->rejected('Staty ukazují 238 bodů, potřeba je 250.')->create();
        $withdrawn = Submission::factory()->for(player('Eva'))->onMilestone('GIGA_MELEE_250')->withdrawn()->create();
        Submission::factory()->for(player('Joe'))->onMilestone('GIGA_MELEE_250')->verified()->create();

        $this->actingAs(admin())->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertSee('Dřívější pokusy')
            ->assertSee('JEN PRO ADMINY')
            ->assertSee("{$rejected->public_id} · Ondra")
            ->assertSee('Důvod: Staty ukazují 238 bodů, potřeba je 250.')
            ->assertSee("{$withdrawn->public_id} · Eva")
            ->assertSee('Zrušit ověření');

        $this->actingAs(player('Curious'))->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertDontSee('Dřívější pokusy')
            ->assertDontSee('Staty ukazují 238 bodů');
    });

    it('shows the current name and the name at the time of sending (HIS-3)', function () {
        $joe = player('Joe the Breeder');
        Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->verified()->create(['player_name_snapshot' => 'JoeBreeds']);

        $this->get(route('milestones.show', 'GIGA_MELEE_250'))
            ->assertSee('OVĚŘENO – objevitel Joe the Breeder')
            ->assertSee('odesláno jako „JoeBreeds“', false);
    });
});
