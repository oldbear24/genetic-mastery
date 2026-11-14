<?php

use App\Enums\EvidenceKind;
use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Services\ClusterProgress;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function () {
    Storage::fake('local');
});

it('stores a pending claim with a public ID that does not count toward progress yet (SUB-4)', function () {
    $joe = player('Joe');
    $stats = Images::png('stats-screen.png', 1280, 720);
    $ancestry = Images::jpeg('ancestry.jpg', 1024, 768);

    $response = submitClaim($joe, 'GIGA_STAMINA_250', [$stats, $ancestry], ['stats', 'ancestry'], 'Bred on The Island.');

    $submission = Submission::query()->firstOrFail();
    $response->assertRedirect(route('submissions.show', $submission))->assertSessionHas('submitted', true);

    expect($submission->public_id)->toMatch('/^S-\d{6}$/')
        ->and($submission->status)->toBe(SubmissionStatus::Pending)
        ->and($submission->milestone->code)->toBe('GIGA_STAMINA_250')
        ->and($submission->user_id)->toBe($joe->id)
        ->and($submission->player_name_snapshot)->toBe('Joe')
        ->and($submission->player_note)->toBe('Bred on The Island.')
        ->and($submission->submitted_at)->not->toBeNull()
        ->and(app(ClusterProgress::class)->verifiedCount())->toBe(0);

    $images = $submission->images;
    expect($images)->toHaveCount(2)
        ->and($images[0]->kind)->toBe(EvidenceKind::Stats)
        ->and($images[0]->mime)->toBe('image/png')
        ->and($images[0]->width)->toBe(1280)
        ->and($images[0]->height)->toBe(720)
        ->and($images[0]->original_name)->toBe('stats-screen.png')
        ->and($images[0]->position)->toBe(1)
        ->and($images[1]->kind)->toBe(EvidenceKind::Ancestry)
        ->and($images[1]->mime)->toBe('image/jpeg');

    foreach ($images as $image) {
        // Random name, never the original one (UPL-4).
        expect($image->storage_key)->toMatch('/^[0-9a-f-]{36}$/')
            ->and($image->path('original'))->not->toContain($image->original_name);

        Storage::disk('local')->assertExists([$image->path('original'), $image->path('display'), $image->path('thumb')]);
        expect($image->sha256)->toBe(hash('sha256', Storage::disk('local')->get($image->path('original'))));
    }

    $this->actingAs($joe)->get(route('submissions.show', $submission))
        ->assertOk()
        ->assertSee("Žádost {$submission->public_id} je odeslaná")
        ->assertSee('Čeká na schválení');
});

it('accepts PNG, JPEG and WEBP', function () {
    submitClaim(player(), 'REX_HEALTH_250', [Images::png(), Images::jpeg(), Images::webp()])->assertSessionHasNoErrors();

    expect(SubmissionImage::query()->pluck('mime')->all())->toBe(['image/png', 'image/jpeg', 'image/webp']);
});

it('makes a full resolution copy without metadata and a thumbnail (UPL-5)', function () {
    submitClaim(player(), 'REX_HEALTH_250', [
        Images::jpegWithExif('GPS-SECRET-49.19N'),
        Images::pngWithText('PNG-SECRET-COMMENT'),
    ])->assertSessionHasNoErrors();

    [$jpeg, $png] = SubmissionImage::query()->orderBy('position')->get()->all();
    $disk = Storage::disk('local');

    expect($disk->get($jpeg->path('original')))->toContain('GPS-SECRET-49.19N')
        ->and($disk->get($jpeg->path('display')))->not->toContain('GPS-SECRET-49.19N')
        ->and($disk->get($png->path('original')))->toContain('PNG-SECRET-COMMENT')
        ->and($disk->get($png->path('display')))->not->toContain('PNG-SECRET-COMMENT');

    // Full resolution copy, thumbnail at most 640 px wide.
    expect(getimagesizefromstring($disk->get($jpeg->path('display')))[0])->toBe(320);

    submitClaim(player('Big'), 'REX_STAMINA_250', [Images::png('wide.png', 2560, 1440)])->assertSessionHasNoErrors();
    $wide = SubmissionImage::query()->where('original_name', 'wide.png')->firstOrFail();
    $display = getimagesizefromstring($disk->get($wide->path('display')));
    expect([$display[0], $display[1]])->toBe([2560, 1440])
        ->and(getimagesizefromstring($disk->get($wide->path('thumb')))[0])->toBe(640)
        ->and(getimagesizefromstring($disk->get($wide->path('thumb')))['mime'])->toBe('image/webp');
});

it('refuses a file that is not acceptable', function (Closure $file, string $message) {
    $response = submitClaim(player(), 'REX_HEALTH_250', [Images::png(), $file()]);

    $response->assertSessionHasErrors('images.1');
    expect(session('errors')->first('images.1'))->toContain($message)
        ->and(Submission::count())->toBe(0)
        ->and(SubmissionImage::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
})->with([
    'PHP file renamed to .png' => [fn () => Images::phpRenamedToPng(), 'není obrázek PNG, JPEG ani WEBP'],
    'SVG' => [fn () => Images::svg(), 'není obrázek PNG, JPEG ani WEBP'],
    'SVG renamed to .png' => [fn () => Images::svg('drawing.png'), 'není obrázek PNG, JPEG ani WEBP'],
    'GIF' => [fn () => Images::gif(), 'není obrázek PNG, JPEG ani WEBP'],
    'GIF renamed to .png' => [fn () => Images::gif('anim.png'), 'není obrázek PNG, JPEG ani WEBP'],
    'over 15 MB' => [fn () => Images::oversized(), 'je větší než 15 MB'],
    'over 40 megapixels' => [fn () => Images::pngBomb(8000, 6000), 'má víc než 40 megapixelů'],
]);

it('refuses the 11th image (UPL-2)', function () {
    $images = array_map(fn ($i) => Images::png("shot-{$i}.png", 64, 64), range(1, 11));

    submitClaim(player(), 'REX_HEALTH_250', $images)
        ->assertSessionHasErrors(['images' => 'Nahrát jde nejvýš 10 obrázků.']);

    expect(Submission::count())->toBe(0);
});

it('accepts exactly 10 images', function () {
    $images = array_map(fn ($i) => Images::png("shot-{$i}.png", 64, 64), range(1, 10));

    submitClaim(player(), 'REX_HEALTH_250', $images)->assertSessionHasNoErrors();

    expect(SubmissionImage::count())->toBe(10);
});

it('refuses more than 60 MB in one submission', function () {
    config(['genetic.upload.max_total_mb' => 1]);

    submitClaim(player(), 'REX_HEALTH_250', [Images::png('a.png')->size(700), Images::png('b.png')->size(700)])
        ->assertSessionHasErrors(['images' => 'Všechny obrázky dohromady smí mít nejvýš 1 MB.']);
});

it('requires at least one image', function () {
    $this->actingAs(player())->post(route('submissions.store'), ['milestone_id' => milestone('REX_HEALTH_250')->id])
        ->assertSessionHasErrors(['images' => 'Přidejte aspoň jeden screenshot.']);
});

it('limits the player note to 1000 characters (SUB-3)', function () {
    submitClaim(player(), 'REX_HEALTH_250', note: str_repeat('a', 1001))
        ->assertSessionHasErrors(['player_note' => 'Pole poznámka hráče smí mít nejvýš 1000 znaků.']);

    submitClaim(player('Other'), 'REX_HEALTH_250', note: str_repeat('a', 1000))->assertSessionHasNoErrors();
});

it('refuses a claim on a verified milestone (SUB-5)', function () {
    Submission::factory()->onMilestone('GIGA_MELEE_250')->verified()->create();

    submitClaim(player(), 'GIGA_MELEE_250')
        ->assertSessionHasErrors(['milestone_id' => 'Tento milník už je ověřený. Vyberte jiný.']);

    expect(Submission::query()->pending()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('refuses a second pending claim of the same player on one milestone (SUB-5)', function () {
    $joe = player();
    submitClaim($joe, 'REX_MELEE_250')->assertSessionHasNoErrors();

    submitClaim($joe, 'REX_MELEE_250')
        ->assertSessionHasErrors(['milestone_id' => 'U tohoto milníku už máte žádost, která čeká na schválení.']);

    expect(Submission::count())->toBe(1);
});

it('allows a claim on a milestone another player is waiting for', function () {
    submitClaim(player('Kira'), 'REX_MELEE_250')->assertSessionHasNoErrors();
    submitClaim(player('Joe'), 'REX_MELEE_250')->assertSessionHasNoErrors();

    expect(Submission::query()->pending()->count())->toBe(2);
});

it('limits players to 10 submissions per hour (SEC-1)', function () {
    $joe = player();
    Submission::factory()->count(10)->for($joe)->withdrawn()->create(['submitted_at' => now()->subMinutes(30)]);

    submitClaim($joe, 'REX_HEALTH_250')
        ->assertSessionHasErrors(['milestone_id' => 'Za hodinu jde poslat nejvýš 10 žádostí. Další pošlete později.']);
});

it('limits players to 30 submissions per day (SEC-1)', function () {
    $joe = player();
    Submission::factory()->count(30)->for($joe)->withdrawn()->create(['submitted_at' => now()->subHours(5)]);

    submitClaim($joe, 'REX_HEALTH_250')
        ->assertSessionHasErrors(['milestone_id' => 'Za den jde poslat nejvýš 30 žádostí. Další pošlete zítra.']);
});

it('has no way to edit a sent claim (SUB-6)', function () {
    $joe = player();
    submitClaim($joe, 'REX_HEALTH_250');
    $submission = Submission::query()->firstOrFail();

    $this->actingAs($joe)->put("/submissions/{$submission->public_id}", ['player_note' => 'changed'])->assertMethodNotAllowed();
    $this->actingAs($joe)->patch("/submissions/{$submission->public_id}", ['player_note' => 'changed'])->assertMethodNotAllowed();
});

it('needs a signed-in player', function () {
    $this->get(route('submissions.create'))->assertRedirect(route('login'));
    $this->post(route('submissions.store'))->assertRedirect(route('login'));
});
