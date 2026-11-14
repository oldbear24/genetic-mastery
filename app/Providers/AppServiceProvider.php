<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Discord\DiscordClient;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Not a singleton: config is read on every resolve, tests change it.
        $this->app->bind(DiscordClient::class, fn () => DiscordClient::fromConfig());
    }

    public function boot(): void
    {
        Carbon::setLocale(config('app.locale'));

        // Catch lazy loading and silently dropped attributes during development.
        Model::shouldBeStrict(! $this->app->isProduction());

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        Gate::define('admin', fn (User $user) => $user->isAdmin());

        $this->configureRateLimits();
    }

    private function configureRateLimits(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('interactions', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
