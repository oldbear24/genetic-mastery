<?php

namespace App\Console\Commands;

use App\Services\Discord\CommandSync;
use App\Services\Discord\DiscordClient;
use App\Services\Discord\DiscordUnavailable;
use App\Services\Discord\GeneticCommands;
use Illuminate\Console\Command;

/**
 * Registers /genetic as a guild command for the cluster server. Guild
 * commands take effect immediately. The maintenance run does the same on
 * its own whenever the definition changes (CommandSync); this command is
 * for doing it right now.
 */
class RegisterDiscordCommands extends Command
{
    protected $signature = 'discord:register-commands';

    protected $description = 'Register the /genetic slash command on the cluster Discord server';

    public function handle(DiscordClient $discord, GeneticCommands $commands): int
    {
        $applicationId = (string) config('services.discord.application_id');

        if ($applicationId === '') {
            $this->error('DISCORD_CLIENT_ID (or DISCORD_APPLICATION_ID) is not set.');

            return self::FAILURE;
        }

        $definitions = $commands->definitions();

        try {
            $registered = $discord->overwriteGuildCommands($applicationId, $definitions);
        } catch (DiscordUnavailable $e) {
            $this->error('Registration failed: '.$e->getMessage());

            return self::FAILURE;
        }

        CommandSync::remember($definitions);

        $this->info('Registered: '.collect($registered)->pluck('name')->implode(', '));

        return self::SUCCESS;
    }
}
