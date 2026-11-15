<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;

it('lets the author withdraw a pending claim (HR-5)', function () {
    $joe = player();
    $submission = Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->create();

    $this->actingAs($joe)->from(route('dashboard'))->post(route('submissions.withdraw', $submission))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status', "Žádost {$submission->public_id} je stažená.");

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Withdrawn)
        ->and($submission->fresh()->withdrawn_at)->not->toBeNull();
});

it('does not let anyone else withdraw a claim', function () {
    $submission = Submission::factory()->for(player('Joe'))->onMilestone('REX_MELEE_250')->create();

    $this->actingAs(player('Kira'))->post(route('submissions.withdraw', $submission))->assertForbidden();
    $this->actingAs(admin())->post(route('submissions.withdraw', $submission))->assertForbidden();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Pending);
});

it('does not withdraw a claim that is no longer pending', function (string $state) {
    $joe = player();
    $submission = Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->{$state}()->create();

    $this->actingAs($joe)->post(route('submissions.withdraw', $submission))->assertForbidden();

    expect($submission->fresh()->status)->not->toBe(SubmissionStatus::Withdrawn);
})->with(['verified', 'rejected']);

it('shows cluster counters, personal stats and own submissions on the dashboard (HR-1, HR-4, HR-6)', function () {
    $joe = player('Joe');
    $kuba = admin('Kuba');
    verifyMilestones(20);

    Submission::factory()->for($joe)->onMilestone('THERI_MELEE_250')->verified($kuba)->create();
    Submission::factory()->for($joe)->onMilestone('THERI_FOOD_250')->rejected('Chybí screenshot předků.', $kuba)->create();
    $pending = Submission::factory()->for($joe)->onMilestone('DEINO_OXYGEN_250')->create();
    Submission::factory()->for(player('Kira'))->onMilestone('DEINO_OXYGEN_250')->create();

    $this->actingAs($joe)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Vítejte zpět, Joe')
        ->assertSee('Jedna vaše žádost čeká na schválení.')
        ->assertSee('Jedna zamítnutá žádost čeká na opravu.')
        ->assertSeeInOrder(['GENETIC MASTERY', '21', '/ 60'])
        ->assertSeeInOrder(['INDO POTENTIAL', '675', '/ 1650'])
        ->assertSeeInOrder(['Vaše objevy', '1', 'z 21 ověřených milníků'])
        ->assertSeeInOrder(['Žebříček', '#2'])
        ->assertSee($pending->public_id)
        ->assertSee('Na stejný milník čeká ještě 1 další žádost.')
        ->assertSee('Důvod: Chybí screenshot předků.')
        ->assertSee('admin Kuba')
        ->assertSee('Stáhnout');
});

it('shows the own layer in the dashboard grid (HR-2, HR-3)', function () {
    $joe = player('Joe');
    Submission::factory()->for($joe)->onMilestone('REX_HEALTH_250')->verified()->create();
    Submission::factory()->for(player('Kira'))->onMilestone('REX_STAMINA_250')->verified()->create();
    Submission::factory()->for($joe)->onMilestone('REX_FOOD_250')->create();
    Submission::factory()->for(player('Eva'))->onMilestone('REX_WEIGHT_250')->create();
    Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->rejected()->create();

    $this->actingAs($joe)->get(route('dashboard'))
        ->assertSee('Rex · Health: ověřeno, váš objev')
        ->assertSee('Rex · Stamina: ověřeno, objevitel Kira')
        ->assertSee('Rex · Food: vaše žádost čeká na schválení')
        ->assertSee('Rex · Weight: čeká na schválení, můžete také zažádat')
        ->assertSee('Rex · Melee Damage: vaše žádost byla zamítnuta, můžete zažádat znovu')
        ->assertSee('Rex · Oxygen: zamčeno, můžete zažádat')
        ->assertSee(route('submissions.create', ['milestone' => 'REX_OXYGEN_250']), false);
});

it('offers only claimable stats in the new claim form (SUB-1)', function () {
    $joe = player('Joe');
    Submission::factory()->for(player('Kira'))->onMilestone('GIGA_HEALTH_250')->verified()->create();
    Submission::factory()->for($joe)->onMilestone('GIGA_MELEE_250')->create();

    $html = $this->actingAs($joe)->get(route('submissions.create', ['milestone' => 'GIGA_STAMINA_250']))
        ->assertOk()
        ->assertSee('Nová žádost')
        ->assertSee('Ověřeno · Kira')
        ->assertSee('Vaše žádost čeká na schválení')
        ->getContent();

    $health = milestone('GIGA_HEALTH_250')->id;
    $melee = milestone('GIGA_MELEE_250')->id;
    $stamina = milestone('GIGA_STAMINA_250')->id;

    expect($html)->toMatch('/name="milestone_id" value="'.$health.'"[^>]*disabled/')
        ->toMatch('/name="milestone_id" value="'.$melee.'"[^>]*disabled/')
        ->toMatch('/name="milestone_id" value="'.$stamina.'"[^>]*checked/');
});

it('lists all own submissions', function () {
    $joe = player();
    Submission::factory()->count(7)->for($joe)->withdrawn()->create();
    Submission::factory()->for(player('Other'))->create();

    $response = $this->actingAs($joe)->get(route('submissions.index'))->assertOk();

    foreach (Submission::where('user_id', $joe->id)->get() as $submission) {
        $response->assertSee($submission->public_id);
    }
    expect(substr_count($response->getContent(), 'Staženo'))->toBeGreaterThanOrEqual(7);
});
