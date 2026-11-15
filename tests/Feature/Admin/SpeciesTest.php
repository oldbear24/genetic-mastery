<?php

use App\Models\AuditLog;
use App\Models\Milestone;
use App\Models\Species;
use App\Models\Submission;
use App\Services\Catalogue;
use App\Services\ClusterProgress;
use App\Services\Discord\CommandSync;
use App\Services\Discord\DiscordUnavailable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Images;

/** PUT /admin/species/{code}; $stats null = all six. */
function saveSpecies(string $code, string $name, bool $active = true, ?array $stats = null, array $extra = []): TestResponse
{
    return test()->actingAs(admin('Catalogue'.uniqid()))->put(route('admin.species.update', $code), [
        'name' => $name,
        'is_active' => $active ? '1' : '0',
        'stats' => $stats ?? array_keys(config('genetic.stats')),
        ...$extra,
    ]);
}

function summary(): array
{
    return app(ClusterProgress::class)->summary();
}

/** An 800 × 400 PNG: left half opaque red, right half fully transparent. */
function halfRedPng(string $name = 'render.png'): UploadedFile
{
    $image = imagecreatetruecolor(800, 400);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 0, 0, 399, 399, imagecolorallocatealpha($image, 220, 30, 30, 0));
    ob_start();
    imagepng($image);

    return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
}

/** Red channel and alpha (0 opaque … 127 transparent) of one pixel of a stored species image. */
function speciesPixel(string $key, int $x, int $y): array
{
    $image = imagecreatefromstring(Storage::disk('local')->get($key));
    $rgba = imagecolorat($image, $x, $y);

    return ['red' => ($rgba >> 16) & 0xFF, 'alpha' => ($rgba >> 24) & 0x7F];
}

function species(string $code): Species
{
    return Species::query()->where('code', $code)->sole();
}

it('shows the catalogue to admins only', function () {
    $this->actingAs(admin())->get(route('admin.species'))
        ->assertOk()
        ->assertSee('Druhy a staty')
        ->assertSee('Tusoteuthis')
        ->assertSee('60 milníků')
        ->assertSee('INDO POTENTIAL nejvýš 1650');

    $this->actingAs(player())->get(route('admin.species'))->assertForbidden();
});

it('switches off a single stat: it stops counting, shows as not used and takes no claims', function () {
    Submission::factory()->onMilestone('TUSO_OXYGEN_250')->verified()->create();
    expect(summary())->toMatchArray(['verified' => 1, 'total' => 60, 'indo_max' => 1650]);

    saveSpecies('TUSO', 'Tusoteuthis', stats: ['HEALTH', 'STAMINA', 'FOOD', 'WEIGHT', 'MELEE'])
        ->assertRedirect(route('admin.species'))
        ->assertSessionHas('status', 'Druh Tusoteuthis je uložený.');

    expect(summary())->toMatchArray(['verified' => 0, 'total' => 59, 'indo' => 150, 'indo_max' => 1625])
        ->and(Submission::query()->verified()->count())->toBe(1); // the claim and its evidence stay

    $this->get('/')->assertSee('Tusoteuthis · Oxygen: v clusteru se nepoužívá');

    $joe = player('Joe');
    $this->actingAs($joe)->get(route('dashboard'))->assertSee('Tusoteuthis · Oxygen: v clusteru se nepoužívá');
    $this->actingAs($joe)->get(route('submissions.create'))
        ->assertOk()
        ->assertDontSee('name="milestone_id" value="'.milestone('TUSO_OXYGEN_250')->id.'"', false)
        ->assertSee('name="milestone_id" value="'.milestone('TUSO_HEALTH_250')->id.'"', false);
    submitClaim($joe, 'TUSO_OXYGEN_250', [Images::png()])->assertSessionHasErrors(['milestone_id' => 'Tento milník se v clusteru nepoužívá. Vyberte jiný.']);

    $entry = AuditLog::query()->where('action', 'species.updated')->sole();
    expect($entry->before)->toBe(['stats' => ['HEALTH', 'STAMINA', 'OXYGEN', 'FOOD', 'WEIGHT', 'MELEE']])
        ->and($entry->after)->toBe(['stats' => ['HEALTH', 'STAMINA', 'FOOD', 'WEIGHT', 'MELEE']]);

    $this->actingAs(admin('Auditor'))->get(route('admin.audit'))
        ->assertSee('Úprava druhu')
        ->assertSee('Health, Stamina, Food, Weight, Melee Damage');
});

it('switches off a whole species until it is switched back on, keeping its claims', function () {
    Submission::factory()->for(player('Joe'))->onMilestone('BASILISK_HEALTH_250')->verified()->create();

    saveSpecies('BASILISK', 'Basilisk', active: false)->assertRedirect(route('admin.species'));

    $progress = app(ClusterProgress::class);
    expect(summary())->toMatchArray(['verified' => 0, 'total' => 54, 'indo_max' => 1500])
        ->and($progress->leaderboard())->toBeEmpty()
        ->and($progress->recentDiscoveries())->toBeEmpty()
        ->and($progress->grid()->keys()->all())->not->toContain('BASILISK');

    $this->get('/')->assertOk()->assertSee('Druh Basilisk je uložený.'); // flash after the redirect
    $this->get('/')->assertOk()->assertDontSee('Basilisk')->assertSee('Tusoteuthis');
    $this->get(route('milestones.show', 'BASILISK_HEALTH_250'))
        ->assertOk()
        ->assertSee('Tento milník se v clusteru nepoužívá');

    saveSpecies('BASILISK', 'Basilisk')->assertRedirect(route('admin.species'));

    expect(summary())->toMatchArray(['verified' => 1, 'total' => 60, 'indo_max' => 1650]);
    $this->get('/')->assertSee('Basilisk');
});

it('refuses to approve a claim on a switched off milestone, which can still be rejected', function () {
    $claim = Submission::factory()->onMilestone('TUSO_OXYGEN_250')->create();
    Milestone::query()->where('code', 'TUSO_OXYGEN_250')->update(['is_active' => false]);

    $this->actingAs(admin())->post(route('admin.submissions.approve', $claim))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Milník je vypnutý'));
    expect($claim->fresh()->status->value)->toBe('pending');

    $this->actingAs(admin('Second'))->post(route('admin.submissions.reject', $claim), ['reason' => 'Oxygen se nepočítá.'])
        ->assertSessionHas('status');
    expect($claim->fresh()->status->value)->toBe('rejected');
});

it('adds a species with all six stats at the end of the catalogue', function () {
    $this->actingAs(admin())->post(route('admin.species.store'), ['code' => 'dodo', 'name' => 'Dodo'])
        ->assertRedirect(route('admin.species'))
        ->assertSessionHas('status', 'Druh Dodo je přidaný.');

    $dodo = Species::query()->where('code', 'DODO')->sole();
    expect($dodo->is_active)->toBeTrue()
        ->and($dodo->milestones()->count())->toBe(6)
        ->and(Milestone::query()->orderByDesc('sort_order')->pluck('code')->first())->toBe('DODO_MELEE_250')
        ->and(summary())->toMatchArray(['total' => 66, 'indo_max' => 1800])
        ->and(AuditLog::query()->where('action', 'species.created')->sole()->after)->toMatchArray(['code' => 'DODO', 'name' => 'Dodo', 'is_active' => true]);

    $this->get('/')->assertSee('Dodo');
});

it('validates the code of a new species', function (string $code) {
    $this->actingAs(admin())->post(route('admin.species.store'), ['code' => $code, 'name' => 'X'])
        ->assertSessionHasErrorsIn('add', 'code');

    expect(Species::query()->count())->toBe(10);
})->with(['taken' => 'REX', 'too short' => 'R', 'starts with a digit' => '1REX', 'diacritics' => 'ŽRALOK', 'too long' => 'ABCDEFGHIJKLMNOPQ', 'empty' => '']);

it('keeps at least one stat on an active species', function () {
    saveSpecies('REX', 'Rex', stats: [])->assertSessionHasErrorsIn('species_REX', ['stats' => 'Aktivní druh musí mít zapnutý aspoň jeden stat.']);

    expect(Milestone::query()->active()->count())->toBe(60)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('renames a species everywhere without changing codes', function () {
    saveSpecies('REX', 'Tyrannosaurus rex')->assertRedirect(route('admin.species'));

    expect(milestone('REX_HEALTH_250')->title())->toBe('Tyrannosaurus rex · Health');
    $this->get('/')->assertSee('Tyrannosaurus rex');

    expect(AuditLog::query()->where('action', 'species.updated')->sole())
        ->before->toBe(['name' => 'Rex'])
        ->after->toBe(['name' => 'Tyrannosaurus rex']);
});

it('writes no audit entry when nothing changed', function () {
    saveSpecies('REX', 'Rex')->assertRedirect(route('admin.species'));

    expect(AuditLog::query()->count())->toBe(0);
});

it('moves a species up and down and keeps milestones in catalogue order', function () {
    $admin = admin();

    $this->actingAs($admin)->post(route('admin.species.move', 'GIGA'), ['direction' => 'up'])->assertRedirect(route('admin.species'));

    expect(Species::query()->ordered()->limit(3)->pluck('code')->all())->toBe(['REX', 'GIGA', 'RAPTOR'])
        ->and(Milestone::orderBy('sort_order')->pluck('code')->get(6))->toBe('GIGA_HEALTH_250');

    // The first species cannot go higher.
    $this->actingAs($admin)->post(route('admin.species.move', 'REX'), ['direction' => 'up'])->assertRedirect(route('admin.species'));

    expect(Species::query()->ordered()->first()->code)->toBe('REX')
        ->and(AuditLog::query()->where('action', 'species.moved')->count())->toBe(1);
});

it('offers only active species in /genetic and re-registers the command after a change', function () {
    Http::fake(['discord.com/api/v10/applications/*' => Http::response([['name' => 'genetic']], 200)]);
    $sync = app(CommandSync::class);

    expect($sync->run())->toBe(['status' => 'registered'])
        ->and($sync->run())->toBe(['status' => 'up to date']);
    Http::assertSentCount(1);

    Species::query()->where('code', 'BASILISK')->update(['is_active' => false]);

    expect($sync->run())->toBe(['status' => 'registered']);
    Http::assertSentCount(2);

    $choices = collect(Http::recorded())->last()[0]->data()[0]['options'][1]['options'][0]['choices'];
    expect(collect($choices)->pluck('value')->all())
        ->toContain('REX')
        ->not->toContain('BASILISK')
        ->toHaveCount(9);
});

it('waits before retrying a failed command registration', function () {
    Http::fake(['discord.com/api/v10/applications/*' => Http::response(['message' => 'Missing Access'], 403)]);
    $sync = app(CommandSync::class);

    expect(fn () => $sync->run())->toThrow(DiscordUnavailable::class);
    expect($sync->run())->toBe(['status' => 'waiting to retry']);
    Http::assertSentCount(1);

    $this->travel(16)->minutes();
    expect(fn () => $sync->run())->toThrow(DiscordUnavailable::class);
    Http::assertSentCount(2);
});

it('uploads a render for a species: scaled down, transparent, served publicly and audited', function () {
    Storage::fake('local');

    saveSpecies('TUSO', 'Tusoteuthis', extra: ['image' => halfRedPng()])
        ->assertRedirect(route('admin.species'))
        ->assertSessionHas('status', 'Druh Tusoteuthis je uložený.');

    $key = species('TUSO')->image;
    expect($key)->toMatch('#^species/TUSO/[0-9a-f]{16}\.webp$#');
    Storage::disk('local')->assertExists($key);

    expect(getimagesizefromstring(Storage::disk('local')->get($key)))->toMatchArray([0 => 400, 1 => 200, 'mime' => 'image/webp'])
        ->and(speciesPixel($key, 50, 100))->toMatchArray(['alpha' => 0])
        ->and(speciesPixel($key, 50, 100)['red'])->toBeGreaterThan(200)
        ->and(speciesPixel($key, 350, 100))->toMatchArray(['alpha' => 127]);

    $url = species('TUSO')->imageUrl();
    expect($url)->toStartWith(url('/species-art/TUSO/'));

    auth()->forgetUser();
    $this->get('/')->assertSee($url, false)->assertDontSee(asset('images/species/tuso.webp'), false);

    $response = $this->get($url)->assertOk()
        ->assertHeader('Content-Type', 'image/webp')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('immutable')
        ->and($response->streamedContent())->toBe(Storage::disk('local')->get($key));

    expect(AuditLog::query()->where('action', 'species.updated')->sole())
        ->before->toBe(['image' => null])
        ->after->toBe(['image' => $key]);
    $this->actingAs(admin('Auditor'))->get(route('admin.audit'))->assertSee('nahraný soubor '.basename($key));
});

it('mirrors a render on request, so the creature faces right', function () {
    Storage::fake('local');

    saveSpecies('TUSO', 'Tusoteuthis', extra: ['image' => halfRedPng(), 'flip' => '1'])->assertRedirect(route('admin.species'));

    $key = species('TUSO')->image;
    expect(speciesPixel($key, 50, 100))->toMatchArray(['alpha' => 127])
        ->and(speciesPixel($key, 350, 100))->toMatchArray(['alpha' => 0]);
});

it('replaces and removes an uploaded render, then the bundled one shows again', function () {
    Storage::fake('local');

    saveSpecies('TUSO', 'Tusoteuthis', extra: ['image' => halfRedPng()]);
    $first = species('TUSO')->imageUrl();
    $firstKey = species('TUSO')->image;

    saveSpecies('TUSO', 'Tusoteuthis', extra: ['image' => halfRedPng('better.png')]);
    $second = species('TUSO')->imageUrl();

    expect($second)->not->toBe($first);
    $this->get($first)->assertNotFound();
    $this->get($second)->assertOk();
    Storage::disk('local')->assertExists($firstKey); // the audit log still refers to it

    saveSpecies('TUSO', 'Tusoteuthis', extra: ['remove_image' => '1'])->assertRedirect(route('admin.species'));

    expect(species('TUSO')->image)->toBeNull()
        ->and(species('TUSO')->imageUrl())->toBe(asset('images/species/tuso.webp'));
    $this->get($second)->assertNotFound();
    $this->get('/')->assertSee(asset('images/species/tuso.webp'), false);
    $this->actingAs(admin())->get(route('admin.species'))->assertOk()->assertDontSee('Odebrat nahraný obrázek');
});

it('refuses a file that is not an acceptable image and changes nothing', function (Closure $file, string $message) {
    Storage::fake('local');

    saveSpecies('REX', 'Tyrannosaurus rex', extra: ['image' => $file()])
        ->assertSessionHasErrorsIn('species_REX', ['image' => $message]);

    expect(species('REX'))->name->toBe('Rex')->image->toBeNull()
        ->and(Storage::disk('local')->allFiles('species'))->toBeEmpty()
        ->and(AuditLog::query()->count())->toBe(0);
})->with([
    'PHP renamed to .png' => [fn () => UploadedFile::fake()->createWithContent('rex.png', '<?php system($_GET["c"]);'), 'Soubor rex.png není obrázek PNG, JPEG ani WEBP.'],
    'GIF' => [fn () => Images::gif('rex.gif'), 'Soubor rex.gif není obrázek PNG, JPEG ani WEBP.'],
    'over 40 megapixels' => [fn () => Images::pngBomb(name: 'rex.png'), 'Obrázek rex.png má víc než 40 megapixelů.'],
]);

it('adds a species together with its render', function () {
    Storage::fake('local');

    $this->actingAs(admin())->post(route('admin.species.store'), ['code' => 'DODO', 'name' => 'Dodo', 'image' => halfRedPng('dodo.png'), 'flip' => '1'])
        ->assertRedirect(route('admin.species'))
        ->assertSessionHas('status', 'Druh Dodo je přidaný.');

    $dodo = species('DODO');
    expect($dodo->image)->toMatch('#^species/DODO/[0-9a-f]{16}\.webp$#')
        ->and(speciesPixel($dodo->image, 350, 100))->toMatchArray(['alpha' => 0])
        ->and(AuditLog::query()->where('action', 'species.created')->sole()->after)->toMatchArray(['image' => $dodo->image]);

    $this->get('/')->assertSee($dodo->imageUrl(), false);
});

it('keeps no file behind when saving the species fails', function () {
    Storage::fake('local');
    $this->mock(Catalogue::class)->shouldReceive('update')->andThrow(new RuntimeException('Database is gone.'));

    saveSpecies('REX', 'Rex', extra: ['image' => halfRedPng()])->assertServerError();

    expect(Storage::disk('local')->allFiles('species'))->toBeEmpty();
});

it('answers 404 for a species render that does not exist', function () {
    $this->get(route('species.art', ['species' => 'REX', 'version' => str_repeat('a', 16)]))->assertNotFound();
    $this->get('/species-art/REX/not-a-version')->assertNotFound();
    $this->get('/species-art/NOPE/'.str_repeat('a', 16))->assertNotFound();
});
