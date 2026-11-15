<?php

use App\Models\Species;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Services\BackupService;
use App\Services\SpeciesArt;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

/*
 * Backups need a real (file based) SQLite database: VACUUM INTO does not
 * run inside the transaction the feature tests use.
 */

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('SQLite backup test; PostgreSQL uses pg_dump.');
    }

    $this->databaseFile = tempnam(sys_get_temp_dir(), 'gm-db').'.sqlite';
    touch($this->databaseFile);
    config(['database.connections.sqlite.database' => $this->databaseFile]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);
    $this->seed(MilestoneSeeder::class);

    Storage::fake('local');
    Storage::fake('backup');
    config(['genetic.backup.disk' => 'backup']);
});

afterEach(function () {
    if (isset($this->databaseFile)) {
        DB::disconnect('sqlite');
        foreach ([$this->databaseFile, "{$this->databaseFile}-wal", "{$this->databaseFile}-shm"] as $file) {
            @unlink($file);
        }
    }
});

it('makes a snapshot and an evidence mirror that pass the restore test', function () {
    submitClaim(player('Joe'), 'GIGA_MELEE_250', [Images::png('stats.png'), Images::png('anc.png')])->assertSessionHasNoErrors();
    expect(SubmissionImage::count())->toBe(2);

    $this->artisan('backup:run')->assertSuccessful();

    $snapshots = app(BackupService::class)->snapshots();
    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0])->toMatch('#^backups/db/genetic-\d{8}-\d{6}\.sqlite\.gz$#');

    foreach (SubmissionImage::all() as $image) {
        Storage::disk('backup')->assertExists('backups/'.$image->path('original'));
    }

    $result = app(BackupService::class)->verify();
    expect($result)->toMatchArray([
        'ok' => true,
        'integrity' => 'ok',
        'milestones' => 60,
        'submissions' => 1,
        'images' => 2,
        'missing_files' => [],
    ]);

    $this->artisan('backup:verify')->expectsOutputToContain('Backup verification OK.')->assertSuccessful();
});

it('copies only new evidence files on the next run', function () {
    submitClaim(player('Joe'), 'GIGA_MELEE_250', [Images::png()])->assertSessionHasNoErrors();
    $first = app(BackupService::class)->run();

    $this->travel(1)->seconds();
    $second = app(BackupService::class)->run();

    expect($first['evidence_copied'])->toBe(3) // original, display copy, thumbnail
        ->and($second['evidence_copied'])->toBe(0);
});

it('mirrors uploaded species images', function () {
    $key = app(SpeciesArt::class)->store(Images::png('dodo.png', 300, 200), 'REX');
    Species::query()->where('code', 'REX')->update(['image' => $key]);

    expect(app(BackupService::class)->run()['evidence_copied'])->toBe(1);
    Storage::disk('backup')->assertExists('backups/'.$key);
});

it('fails the restore test when evidence is missing from the mirror', function () {
    submitClaim(player('Joe'), 'GIGA_MELEE_250', [Images::png()])->assertSessionHasNoErrors();
    app(BackupService::class)->run();
    $image = SubmissionImage::query()->sole();
    Storage::disk('backup')->delete('backups/'.$image->path('original'));

    $this->artisan('backup:verify')->expectsOutputToContain('FAILED')->assertFailed();
});

it('keeps snapshots for 30 days', function () {
    Storage::disk('backup')->put('backups/db/genetic-20200101-033000.sqlite.gz', 'old');
    Storage::disk('backup')->put('backups/db/'.'genetic-'.now()->utc()->subDays(10)->format('Ymd-His').'.sqlite.gz', 'recent');

    $result = app(BackupService::class)->run();

    expect($result['pruned'])->toBe(1);
    Storage::disk('backup')->assertMissing('backups/db/genetic-20200101-033000.sqlite.gz');
    expect(app(BackupService::class)->snapshots())->toHaveCount(2);
});

it('restores data that matches the live database', function () {
    Submission::factory()->count(3)->onMilestone('REX_HEALTH_250')->withdrawn()->create();
    app(BackupService::class)->run();

    expect(app(BackupService::class)->verify()['submissions'])->toBe(Submission::count());
});
