<?php

use App\Models\Submission;
use App\Models\SubmissionImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Images;

function assertSecurityHeaders(TestResponse $response): void
{
    $csp = (string) $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toBe('')
        ->and($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('unsafe-eval')
        ->and($response->headers->get('Strict-Transport-Security'))->toStartWith('max-age=')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
}

it('sends CSP, HSTS and X-Frame-Options on every page (SEC-5)', function () {
    Storage::fake('local');
    $joe = player('Joe');
    submitClaim($joe, 'REX_HEALTH_250', [Images::png()]);
    $submission = Submission::query()->sole();
    $image = SubmissionImage::query()->sole();

    $responses = [
        $this->get('/'),
        $this->get(route('leaderboard')),
        $this->get(route('login')),
        $this->get(route('milestones.show', 'REX_HEALTH_250')),
        $this->get('/does-not-exist'),
        $this->actingAs($joe)->get(route('dashboard')),
        $this->actingAs($joe)->get(route('submissions.create')),
        $this->actingAs($joe)->get(route('submissions.show', $submission)),
        $this->actingAs($joe)->get(route('evidence.show', [$image, 'thumb'])),
        $this->actingAs(admin())->get(route('admin.queue')),
        $this->actingAs(admin('A2'))->get(route('admin.audit')),
        $this->postJson('/discord/interactions', ['type' => 1]),
    ];

    foreach ($responses as $response) {
        assertSecurityHeaders($response);
    }
});

it('keeps the strict sandbox CSP of evidence responses', function () {
    Storage::fake('local');
    $joe = player();
    submitClaim($joe, 'REX_HEALTH_250', [Images::png()]);

    $csp = $this->actingAs($joe)->get(route('evidence.show', [SubmissionImage::query()->sole(), 'display']))->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'none'")->toContain('sandbox');
});

it('allows only what the pages need in the CSP', function () {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("default-src 'self'")
        ->toContain("script-src 'self'")
        ->toContain("img-src 'self' data: blob: https://cdn.discordapp.com")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain("form-action 'self'");
});

it('gives players 403 on every admin address (acceptance)', function () {
    $claim = Submission::factory()->onMilestone('REX_MELEE_250')->verified()->create();
    $player = player('Joe');
    $adminRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.'));

    expect($adminRoutes->count())->toBeGreaterThanOrEqual(9);

    foreach ($adminRoutes as $route) {
        $uri = '/'.str_replace('{submission}', $claim->public_id, $route->uri());
        $method = in_array('POST', $route->methods(), true) ? 'post' : 'get';

        $this->actingAs($player)->{$method}($uri, ['reason' => 'x', 'revoke_reason' => 'x', 'body' => 'x', 'visibility' => 'public'])
            ->assertForbidden();
    }

    expect($claim->fresh()->status->value)->toBe('verified');
});

it('escapes player and admin text everywhere (SEC-3)', function () {
    $claim = Submission::factory()->onMilestone('REX_MELEE_250')->create(['player_note' => '<script>alert("x")</script>']);

    $this->actingAs(admin())->get(route('admin.submissions.show', $claim))
        ->assertDontSee('<script>alert("x")</script>', false)
        ->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false);
});

it('tags every response and log line with a request ID (NF-5)', function () {
    $response = $this->get('/');
    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');

    $this->get('/', ['X-Request-Id' => 'proxy-req-12345'])->assertHeader('X-Request-Id', 'proxy-req-12345');
    expect(Log::sharedContext())->toMatchArray(['request_id' => 'proxy-req-12345']);

    $this->get('/', ['X-Request-Id' => "bad\nvalue"])->assertHeader('X-Request-Id');
    expect(Log::sharedContext()['request_id'])->not->toContain("\n");
});

it('rate limits the interactions endpoint (SEC-1)', function () {
    for ($i = 0; $i < 120; $i++) {
        $this->postJson('/discord/interactions', ['type' => 1]);
    }

    $this->postJson('/discord/interactions', ['type' => 1])->assertStatus(429);
});

it('shows pagination in Czech', function () {
    $joe = player();
    Submission::factory()->count(30)->for($joe)->withdrawn()->create();

    $this->actingAs($joe)->get(route('submissions.index'))
        ->assertSee('Zobrazeno')
        ->assertSee('výsledků')
        ->assertDontSee('Showing');
});
