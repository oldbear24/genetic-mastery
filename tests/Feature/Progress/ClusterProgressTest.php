<?php

use App\Enums\MilestoneState;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Services\ClusterProgress;

it('computes progress and Indo Potential from verified milestones', function (int $verified, int $indo) {
    verifyMilestones($verified);

    $summary = app(ClusterProgress::class)->summary();

    expect($summary['verified'])->toBe($verified)
        ->and($summary['total'])->toBe(54)
        ->and($summary['indo'])->toBe($indo)
        ->and($summary['indo_max'])->toBe(1500);
})->with([
    'nothing verified' => [0, 150],
    '18 verified' => [18, 600],
    '23 verified' => [23, 725],
    'all 54 verified' => [54, 1500],
]);

it('shows V / 54 and 150 + 25 × V on the home page', function (int $verified, int $indo) {
    verifyMilestones($verified);

    $this->get('/')
        ->assertOk()
        ->assertSeeInOrder(['GENETIC MASTERY', (string) $verified, '/ 54'])
        ->assertSeeInOrder(['INDO POTENTIAL', (string) $indo, '/ 1500']);
})->with([
    [0, 150],
    [23, 725],
    [54, 1500],
]);

it('does not count pending, rejected, withdrawn or revoked submissions', function () {
    Submission::factory()->onMilestone('REX_HEALTH_250')->create();
    Submission::factory()->onMilestone('REX_STAMINA_250')->rejected()->create();
    Submission::factory()->onMilestone('REX_OXYGEN_250')->withdrawn()->create();
    Submission::factory()->onMilestone('REX_FOOD_250')->create(['status' => SubmissionStatus::Revoked]);

    expect(app(ClusterProgress::class)->verifiedCount())->toBe(0)
        ->and(app(ClusterProgress::class)->indoPotential())->toBe(150);
});

it('uses the Indo formula from the config', function () {
    config(['genetic.indo.base' => 100, 'genetic.indo.per_milestone' => 10]);
    verifyMilestones(3);

    expect(app(ClusterProgress::class)->indoPotential())->toBe(130);
});

it('derives the milestone state from submissions', function () {
    $locked = milestone('REX_HEALTH_250');
    $pending = milestone('REX_STAMINA_250');
    $verified = milestone('REX_OXYGEN_250');
    $rejectedOnly = milestone('REX_FOOD_250');

    Submission::factory()->create(['milestone_id' => $pending->id]);
    Submission::factory()->verified()->create(['milestone_id' => $verified->id]);
    Submission::factory()->create(['milestone_id' => $verified->id, 'status' => SubmissionStatus::Superseded]);
    Submission::factory()->rejected()->create(['milestone_id' => $rejectedOnly->id]);

    expect($locked->fresh()->state())->toBe(MilestoneState::Locked)
        ->and($pending->fresh()->state())->toBe(MilestoneState::Pending)
        ->and($verified->fresh()->state())->toBe(MilestoneState::Verified)
        ->and($rejectedOnly->fresh()->state())->toBe(MilestoneState::Locked);

    $states = app(ClusterProgress::class)->milestones()->keyBy('code')->map->state();

    expect($states['REX_HEALTH_250'])->toBe(MilestoneState::Locked)
        ->and($states['REX_STAMINA_250'])->toBe(MilestoneState::Pending)
        ->and($states['REX_OXYGEN_250'])->toBe(MilestoneState::Verified)
        ->and($states['REX_FOOD_250'])->toBe(MilestoneState::Locked);
});
