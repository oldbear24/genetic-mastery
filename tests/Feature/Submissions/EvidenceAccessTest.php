<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function () {
    Storage::fake('local');
});

function claimWithImage(string $code = 'REX_HEALTH_250', ?User $author = null): array
{
    $author ??= player('Author');
    submitClaim($author, $code, [Images::png('stats.png', 640, 360)])->assertSessionHasNoErrors();
    auth()->logout();

    $submission = Submission::query()->latest('id')->firstOrFail();

    return [$submission, $submission->images->first(), $author];
}

it('serves the copy of a verified claim without login, with nosniff (UPL-6)', function () {
    [$submission, $image] = claimWithImage();
    $submission->update(['status' => SubmissionStatus::Verified, 'reviewed_by' => admin()->id, 'reviewed_at' => now()]);

    foreach (['display', 'thumb'] as $variant) {
        $response = $this->get(route('evidence.show', [$image, $variant]));

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Type', $variant === 'thumb' ? 'image/webp' : 'image/png');
        expect($response->headers->get('Cache-Control'))->toContain('private');
    }

    $this->get(route('submissions.show', $submission))->assertOk();
});

it('does not serve a pending claim to guests or other players (UPL-6)', function () {
    [$submission, $image] = claimWithImage();

    $this->get(route('evidence.show', [$image, 'display']))->assertForbidden();
    $this->get(route('evidence.show', [$image, 'thumb']))->assertForbidden();
    $this->actingAs(player('Stranger'))->get(route('evidence.show', [$image, 'display']))->assertForbidden();

    auth()->logout();
    $this->get(route('submissions.show', $submission))->assertForbidden();
    $this->actingAs(player('Stranger2'))->get(route('submissions.show', $submission))->assertForbidden();
});

it('serves a pending claim to its author and to admins', function () {
    [$submission, $image, $author] = claimWithImage();

    $this->actingAs($author)->get(route('evidence.show', [$image, 'display']))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs(admin())->get(route('evidence.show', [$image, 'display']))->assertOk();
    $this->actingAs($author)->get(route('submissions.show', $submission))->assertOk();
});

it('keeps rejected claims private', function () {
    [$submission, $image, $author] = claimWithImage();
    $submission->update(['status' => SubmissionStatus::Rejected, 'rejection_reason' => 'Chybí předci.']);

    $this->get(route('evidence.show', [$image, 'display']))->assertForbidden();
    $this->actingAs($author)->get(route('submissions.show', $submission))->assertOk()->assertSee('Důvod: Chybí předci.');
});

it('gives originals only to admins, as a download (UPL-5, OPR-6)', function () {
    [$submission, $image, $author] = claimWithImage();
    $submission->update(['status' => SubmissionStatus::Verified, 'reviewed_by' => admin('Rev')->id, 'reviewed_at' => now()]);

    $this->get(route('evidence.show', [$image, 'original']))->assertForbidden();
    $this->actingAs($author)->get(route('evidence.show', [$image, 'original']))->assertForbidden();

    $response = $this->actingAs(admin())->get(route('evidence.show', [$image, 'original']));
    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;')
        ->and($response->streamedContent())->toBe(Storage::disk('local')->get($image->path('original')));
});

it('returns 404 for unknown images and variants', function () {
    [, $image] = claimWithImage();

    $this->get('/evidence/'.fake()->uuid().'/display')->assertNotFound();
    $this->get("/evidence/{$image->storage_key}/exif")->assertNotFound();
});

it('never deletes evidence when the claim changes state (UPL-9)', function () {
    [$submission, $image, $author] = claimWithImage();

    $this->actingAs($author)->post(route('submissions.withdraw', $submission))->assertRedirect();

    expect($submission->fresh()->status)->toBe(SubmissionStatus::Withdrawn)
        ->and(SubmissionImage::query()->whereKey($image->id)->exists())->toBeTrue();
    Storage::disk('local')->assertExists([$image->path('original'), $image->path('display'), $image->path('thumb')]);
});
