<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\RefreshDiscordMembership;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Discord interactions and ops hooks live outside the "web" group
            // (no session, no CSRF): they authenticate with signatures or tokens.
            Route::group([], base_path('routes/discord.php'));
            Route::group([], base_path('routes/ops.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->append(SecurityHeaders::class);

        // Behind a TLS-terminating proxy: TRUSTED_PROXIES=* (or a list of IPs),
        // so HTTPS, client IPs (rate limits) and secure cookies work.
        $trusted = env('TRUSTED_PROXIES');
        if (filled($trusted)) {
            $middleware->trustProxies(at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)));
        }

        $middleware->web(append: [
            RefreshDiscordMembership::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('discord/*') || $request->expectsJson(),
        );
    })->create();
