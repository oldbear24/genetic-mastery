<?php

use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Services\BackupService;
use Database\Seeders\MilestoneSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

/*
 * MySQL / MariaDB backup without mysqldump (shared hosting has no exec).
 * Runs in CI against the MySQL and MariaDB services.
 */

beforeEach(function () {
    if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('MySQL / MariaDB only.');
    }

    Artisan::call('migrate:fresh', ['--force' => true]);
    $this->seed(MilestoneSeeder::class);
    Storage::fake('local');
    Storage::fake('backup');
    config(['genetic.backup.disk' => 'backup']);
});

it('dumps schema and data in PHP and passes the restore test', function () {
    submitClaim(player('Joe'), 'GIGA_MELEE_250', [Images::png('stats.png')], note: "Řádek 1\nŘádek 2 s 'apostrofem'")->assertSessionHasNoErrors();
    $this->actingAs(admin('Kuba'))->post(route('admin.submissions.reject', Submission::query()->sole()), ['reason' => 'Chybí předci.']);

    $result = app(BackupService::class)->run();

    expect($result['snapshot'])->toEndWith('.sql.gz');

    $dump = gzdecode(Storage::disk('backup')->get($result['snapshot']));
    expect($dump)->toContain('CREATE TABLE `submissions`')
        ->toContain('INSERT INTO `milestones`')
        ->toContain('-- Dump completed')
        ->not->toContain('INSERT INTO `sessions`')
        ->not->toContain('`verified_milestone_key`) VALUES'); // generated columns are not inserted

    $verify = app(BackupService::class)->verify();
    expect($verify)->toMatchArray([
        'ok' => true,
        'integrity' => 'ok',
        'milestones' => 54,
        'submissions' => 1,
        'images' => 1,
    ]);

    Storage::disk('backup')->assertExists('backups/'.SubmissionImage::query()->sole()->path('original'));
});

it('restores into an empty database with the same rows', function () {
    Submission::factory()->count(3)->onMilestone('REX_HEALTH_250')->withdrawn()->create();
    $result = app(BackupService::class)->run();
    $before = Submission::query()->orderBy('id')->pluck('public_id')->all();

    // Import the dump the way the mysql client or phpMyAdmin would.
    DB::unprepared(gzdecode(Storage::disk('backup')->get($result['snapshot'])));

    expect(Submission::query()->orderBy('id')->pluck('public_id')->all())->toBe($before)
        ->and(DB::table('milestones')->count())->toBe(54);
});
