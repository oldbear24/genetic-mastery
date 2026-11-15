<?php

namespace App\Services\Discord;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Keeps the registered /genetic command in step with its definition. The
 * species choices come from the catalogue, so an admin change on
 * /admin/species alters the definition; the next maintenance run notices
 * the new hash and re-registers (a PUT of the guild commands, idempotent).
 * Never called from a request. After a failure it waits before retrying.
 */
class CommandSync
{
    public const HASH = 'discord:commands-hash';

    private const RETRY_AT = 'discord:commands-retry-at';

    private const RETRY_MINUTES = 15;

    public function __construct(
        private readonly DiscordClient $discord,
        private readonly GeneticCommands $commands,
    ) {}

    /** @return array{status: string} */
    public function run(): array
    {
        $applicationId = (string) config('services.discord.application_id');

        if ($applicationId === '' || blank(config('services.discord.bot_token')) || blank(config('services.discord.guild_id'))) {
            return ['status' => 'not configured'];
        }

        $definitions = $this->commands->definitions();
        $hash = self::hash($definitions);

        if (Cache::get(self::HASH) === $hash) {
            return ['status' => 'up to date'];
        }

        if (Cache::get(self::RETRY_AT, 0) > now()->getTimestamp()) {
            return ['status' => 'waiting to retry'];
        }

        try {
            $this->discord->overwriteGuildCommands($applicationId, $definitions);
        } catch (Throwable $e) {
            Cache::put(self::RETRY_AT, now()->addMinutes(self::RETRY_MINUTES)->getTimestamp(), now()->addMinutes(self::RETRY_MINUTES));

            throw $e;
        }

        self::remember($definitions);

        return ['status' => 'registered'];
    }

    /** Called after any successful registration (also the artisan command). @param list<array<string, mixed>> $definitions */
    public static function remember(array $definitions): void
    {
        Cache::forever(self::HASH, self::hash($definitions));
        Cache::forget(self::RETRY_AT);
    }

    /** @param list<array<string, mixed>> $definitions */
    private static function hash(array $definitions): string
    {
        return hash('sha256', (string) json_encode($definitions));
    }
}
