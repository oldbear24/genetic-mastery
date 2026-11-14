<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Local development only: a signed, short-lived login link for a seeded
 * user, so the app can be tried without a Discord application. The route
 * behind the link exists only when APP_ENV=local.
 */
class DevLoginLink extends Command
{
    protected $signature = 'dev:login {user : Discord ID or display name}';

    protected $description = 'Print a 10 minute login link for a user (APP_ENV=local only)';

    public function handle(): int
    {
        if (! app()->isLocal()) {
            $this->error('dev:login works only with APP_ENV=local.');

            return self::FAILURE;
        }

        $needle = (string) $this->argument('user');
        $user = User::query()->where('discord_id', $needle)->orWhere('display_name', $needle)->first();

        if ($user === null) {
            $this->error("No user '{$needle}'. Seed demo data: php artisan db:seed --class=DemoSeeder");

            return self::FAILURE;
        }

        $this->line(URL::temporarySignedRoute('dev.login', now()->addMinutes(10), ['user' => $user->id]));

        return self::SUCCESS;
    }
}
