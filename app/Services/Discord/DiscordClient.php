<?php

namespace App\Services\Discord;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Discord REST API, authenticated as the bot.
 * The bot token never leaves this class and is never logged.
 */
class DiscordClient
{
    public function __construct(
        private readonly ?string $botToken,
        private readonly ?string $guildId,
        private readonly string $apiBase,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            config('services.discord.bot_token'),
            config('services.discord.guild_id'),
            rtrim((string) config('services.discord.api_base'), '/'),
        );
    }

    /**
     * Get Guild Member for the cluster server. Returns null when the user is
     * not a member. Does not need the privileged Server Members intent.
     *
     * @return array{user?: array<string, mixed>, nick?: string|null, roles: list<string>, avatar?: string|null}|null
     *
     * @throws DiscordUnavailable
     */
    public function guildMember(string $userId): ?array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->get("/guilds/{$this->guildId}/members/{$userId}"));

        if ($response->status() === 404) {
            return null;
        }

        $this->ensureSuccessful($response);

        $member = $response->json();
        $member['roles'] = array_values(array_map('strval', $member['roles'] ?? []));

        return $member;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws DiscordUnavailable
     */
    public function createMessage(string $channelId, array $payload): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->post("/channels/{$channelId}/messages", $payload));
        $this->ensureSuccessful($response);

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws DiscordUnavailable
     */
    public function editMessage(string $channelId, string $messageId, array $payload): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->patch("/channels/{$channelId}/messages/{$messageId}", $payload));
        $this->ensureSuccessful($response);

        return $response->json();
    }

    /**
     * Replace all guild commands of the application (bulk overwrite).
     *
     * @param  list<array<string, mixed>>  $commands
     * @return list<array<string, mixed>>
     *
     * @throws DiscordUnavailable
     */
    public function overwriteGuildCommands(string $applicationId, array $commands): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->put("/applications/{$applicationId}/guilds/{$this->guildId}/commands", $commands));
        $this->ensureSuccessful($response);

        return $response->json();
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws DiscordUnavailable
     */
    private function send(callable $request): Response
    {
        if (blank($this->botToken) || blank($this->guildId)) {
            throw new DiscordUnavailable('Discord bot token or guild ID is not configured.');
        }

        $http = Http::baseUrl($this->apiBase)
            ->withHeaders([
                'Authorization' => 'Bot '.$this->botToken,
                'User-Agent' => 'DiscordBot ('.config('app.url').', 1.0)',
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(5);

        try {
            return $request($http);
        } catch (ConnectionException $e) {
            throw new DiscordUnavailable('Discord is unreachable: '.$e->getMessage());
        }
    }

    /** @throws DiscordUnavailable */
    private function ensureSuccessful(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $retryAfter = $response->status() === 429 ? (float) ($response->json('retry_after') ?? 1) : null;

        throw new DiscordUnavailable(
            sprintf('Discord API error %d: %s', $response->status(), mb_substr((string) $response->json('message', $response->body()), 0, 200)),
            $response->status(),
            $retryAfter,
        );
    }
}
