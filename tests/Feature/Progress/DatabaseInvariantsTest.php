<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Database\UniqueConstraintViolationException;

it('allows at most one verified submission per milestone (database index)', function () {
    Submission::factory()->onMilestone('GIGA_MELEE_250')->verified()->create();

    expect(fn () => Submission::factory()->onMilestone('GIGA_MELEE_250')->verified()->create())
        ->toThrow(UniqueConstraintViolationException::class);

    expect(Submission::query()->verified()->count())->toBe(1);
});

it('allows verified submissions on different milestones', function () {
    Submission::factory()->onMilestone('GIGA_MELEE_250')->verified()->create();
    Submission::factory()->onMilestone('GIGA_HEALTH_250')->verified()->create();

    expect(Submission::query()->verified()->count())->toBe(2);
});

it('allows a player only one pending claim per milestone (database index)', function () {
    $joe = player();
    Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->create();

    expect(fn () => Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows pending claims from different players on the same milestone', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('REX_MELEE_250')->create();
    Submission::factory()->for(player('Kira'))->onMilestone('REX_MELEE_250')->create();

    expect(Submission::query()->pending()->count())->toBe(2);
});

it('allows a new pending claim after the previous one was rejected or withdrawn', function () {
    $joe = player();
    Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->rejected()->create();
    Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->withdrawn()->create();
    Submission::factory()->for($joe)->onMilestone('REX_MELEE_250')->create();

    expect(Submission::query()->where('user_id', $joe->id)->count())->toBe(3);
});

it('gives every submission a readable public ID', function () {
    $submission = Submission::factory()->create();

    expect($submission->fresh()->public_id)->toBe(sprintf('S-%06d', $submission->id))
        ->and($submission->fresh()->public_id)->toMatch('/^S-\d{6}$/');
});

it('only allows the transitions from the state diagram', function () {
    expect(SubmissionStatus::Pending->canTransitionTo(SubmissionStatus::Verified))->toBeTrue()
        ->and(SubmissionStatus::Pending->canTransitionTo(SubmissionStatus::Superseded))->toBeTrue()
        ->and(SubmissionStatus::Verified->canTransitionTo(SubmissionStatus::Revoked))->toBeTrue()
        ->and(SubmissionStatus::Superseded->canTransitionTo(SubmissionStatus::Pending))->toBeTrue()
        ->and(SubmissionStatus::Rejected->canTransitionTo(SubmissionStatus::Pending))->toBeTrue()
        ->and(SubmissionStatus::Revoked->canTransitionTo(SubmissionStatus::Pending))->toBeTrue()
        ->and(SubmissionStatus::Verified->canTransitionTo(SubmissionStatus::Rejected))->toBeFalse()
        ->and(SubmissionStatus::Withdrawn->canTransitionTo(SubmissionStatus::Pending))->toBeFalse()
        ->and(SubmissionStatus::Rejected->canTransitionTo(SubmissionStatus::Verified))->toBeFalse();
});
