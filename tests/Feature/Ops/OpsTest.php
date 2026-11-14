<?php

use App\Http\Middleware\RelocateUnreadableUploads;
use App\Models\DiscordOutbox;
use App\Models\Submission;
use App\Models\SubmissionImage;
use App\Services\BackupService;
use App\Services\Maintenance;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

const CRON = 'cron-token-0123456789abcdef0123456789abcdef';
const DEPLOY = 'deploy-token-0123456789abcdef0123456789abcdef';

beforeEach(function () {
    config(['genetic.ops.cron_token' => CRON, 'genetic.ops.deploy_token' => DEPLOY]);
    Storage::fake('local');
});

describe('access', function () {
    it('does not exist without a token, with a short token or with a wrong one', function () {
        config(['genetic.ops.cron_token' => null]);
        $this->get('/ops/cron/anything')->assertNotFound();

        config(['genetic.ops.cron_token' => 'short']);
        $this->get('/ops/cron/short')->assertNotFound();

        config(['genetic.ops.cron_token' => CRON]);
        $this->get('/ops/cron/'.CRON.'x')->assertNotFound();
        $this->get('/ops/deploy/check/'.CRON)->assertNotFound(); // the cron token cannot deploy
        $this->get('/ops/deploy/drop-database/'.DEPLOY)->assertNotFound();
    });

    it('needs no session or CSRF and sends no cookies', function () {
        $response = $this->get('/ops/cron/'.CRON)->assertOk();

        expect($response->headers->getCookies())->toBeEmpty()
            ->and($response->headers->get('Content-Type'))->toStartWith('text/plain');
    });
});

describe('cron', function () {
    it('runs the maintenance: the Discord outbox goes out', function () {
        $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create();
        $this->actingAs(admin())->post(route('admin.submissions.approve', $claim));
        auth()->logout();
        Http::fake(['discord.com/api/v10/channels/*' => Http::response(['id' => '42'], 200)]);

        $this->get('/ops/cron/'.CRON)->assertOk()->assertSee('"sent": 1', false);

        expect(DiscordOutbox::query()->sole()->status)->toBe('sent');
    });

    it('backs up when the last snapshot is older than a day, then not again', function () {
        $backups = Mockery::mock(BackupService::class);
        $backups->shouldReceive('latestSnapshotTime')->andReturn(null, now(), now(), now());
        $backups->shouldReceive('run')->once()->andReturn(['snapshot' => 'backups/db/x', 'evidence_copied' => 0, 'pruned' => 0]);
        $backups->shouldReceive('verify')->andReturn(['ok' => true]);
        $this->app->instance(BackupService::class, $backups);

        $first = app(Maintenance::class)->tick();
        $second = app(Maintenance::class)->tick();

        expect($first)->toHaveKey('backup')
            ->and($second)->not->toHaveKey('backup');
    });

    it('skips a run while another one holds the lock', function () {
        $lock = Cache::lock('maintenance:tick', 60);
        $lock->get();

        expect(app(Maintenance::class)->tick())->toBe(['skipped' => 'another run is still in progress']);

        $lock->release();
    });
});

describe('deploy', function () {
    it('checks the environment without printing secrets', function () {
        config(['services.discord.bot_token' => 'super-secret-bot-token-value', 'services.discord.client_secret' => 'super-secret-client']);
        Http::fake(['discord.com/api/v10/users/@me' => Http::response(['username' => 'GeneticBot'], 200)]);

        $response = $this->get('/ops/deploy/check/'.DEPLOY);

        $response->assertSee('PHP >= 8.4', false)
            ->assertSee('database connection')
            ->assertSee('milestone catalogue')
            ->assertSee('max_file_uploads')
            ->assertSee('bot GeneticBot')
            ->assertSee('Upload test: POST an image')
            ->assertDontSee('super-secret-bot-token-value')
            ->assertDontSee('super-secret-client');
    });

    it('runs migrations and the milestone seed', function () {
        $this->get('/ops/deploy/migrate/'.DEPLOY)
            ->assertOk()
            ->assertSee('php artisan migrate')
            ->assertSee('php artisan db:seed');
    });

    it('caches and clears the configuration', function () {
        $this->get('/ops/deploy/clear/'.DEPLOY)->assertOk()->assertSee('optimize:clear');
    });

    it('proves that uploads can be read on this host', function () {
        $this->post('/ops/deploy/upload-test/'.DEPLOY, ['image' => Images::png('test.png', 640, 360)])
            ->assertOk()
            ->assertSee('OK upload readable, image/png 640×360');

        $this->post('/ops/deploy/upload-test/'.DEPLOY, ['image' => Images::phpRenamedToPng()])
            ->assertStatus(422)
            ->assertSee('není obrázek PNG, JPEG ani WEBP');
    });
});

describe('uploads under open_basedir', function () {
    afterEach(function () {
        RelocateUnreadableUploads::$readable = null;
    });

    it('moves unreadable upload temp files into storage before validation and removes them afterwards', function () {
        RelocateUnreadableUploads::$readable = fn (string $path) => ! str_contains($path, sys_get_temp_dir()) && ! str_contains($path, 'php');

        $upload = Images::png('stats.png', 800, 450);
        $tempPath = $upload->getPathname();

        submitClaim(player('Joe'), 'GIGA_MELEE_250', [$upload])->assertSessionHasNoErrors();

        expect(file_exists($tempPath))->toBeFalse(); // moved away from the unreadable temp dir

        $image = SubmissionImage::query()->sole();
        expect($image->original_name)->toBe('stats.png')
            ->and($image->width)->toBe(800);
        Storage::disk('local')->assertExists($image->path('original'));
        expect(glob(storage_path('app/tmp/upload-*')))->toBe([]);
    });
});

it('sends the announcement right after the decision when configured', function () {
    config(['genetic.discord_send_after_response' => true]);
    $this->withoutDefer();
    Http::fake(['discord.com/api/v10/channels/*' => Http::response(['id' => '77'], 200)]);
    $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create();

    $this->actingAs(admin())->post(route('admin.submissions.approve', $claim))->assertSessionHas('status');

    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/messages'));
    expect(DiscordOutbox::query()->sole()->status)->toBe('sent');
});

it('leaves the announcement to the cron by default', function () {
    $this->withoutDefer();
    Http::fake();
    $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create();

    $this->actingAs(admin())->post(route('admin.submissions.approve', $claim));

    Http::assertNothingSent();
    expect(DiscordOutbox::query()->sole()->status)->toBe('pending');
});

it('resolves a relative SQLite path against the app root', function () {
    $previous = getenv('DB_DATABASE');
    putenv('DB_DATABASE=storage/database/database.sqlite');
    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = 'storage/database/database.sqlite';

    try {
        $config = require config_path('database.php');
    } finally {
        putenv('DB_DATABASE='.$previous);
        $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $previous;
    }

    expect($config['connections']['sqlite']['database'])->toBe(base_path('storage/database/database.sqlite'));
});
