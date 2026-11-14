<?php

namespace App\Services\Discord;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Keeps membership, admin flag and names in sync with the Discord server.
 * Runs on login (AUTH-2, AUTH-3, AUTH-5) and then at most every
 * `genetic.roles_refresh_minutes` minutes per user (AUTH-4).
 */
class MembershipSync
{
    public function __construct(private readonly DiscordClient $discord) {}

    /**
     * Create or update the user after a Discord login. Returns null when the
     * user is not a member of the cluster server.
     *
     * @param  array{id: string, username: string, global_name: string|null, avatar: string|null}  $identity
     *
     * @throws DiscordUnavailable when membership cannot be verified
     */
    public function login(array $identity): ?User
    {
        $member = $this->discord->guildMember($identity['id']);

        if ($member === null) {
            User::query()->where('discord_id', $identity['id'])->update(['is_member' => false, 'roles_checked_at' => now()]);

            return null;
        }

        $user = User::query()->firstOrNew(['discord_id' => $identity['id']]);
        $user->fill([
            'username' => $identity['username'],
            'global_name' => $identity['global_name'],
            'avatar_hash' => $identity['avatar'],
            'last_login_at' => now(),
        ]);
        $this->applyMember($user, $member);
        $user->save();

        return $user;
    }

    /** Whether the stored membership and roles are older than the refresh interval. */
    public function isStale(User $user): bool
    {
        return $user->roles_checked_at === null
            || $user->roles_checked_at->lte(now()->subMinutes((int) config('genetic.roles_refresh_minutes')));
    }

    /**
     * Re-check membership and roles. When Discord is down the last known
     * state stays (NF-2: an outage must not block admins) and the next try
     * happens after a minute.
     */
    public function refresh(User $user): void
    {
        $backoffKey = "discord:member-refresh-backoff:{$user->id}";

        if (Cache::has($backoffKey)) {
            return;
        }

        try {
            $member = $this->discord->guildMember($user->discord_id);
        } catch (DiscordUnavailable $e) {
            Log::warning('Discord membership refresh failed, keeping last known roles.', [
                'user_id' => $user->id,
                'status' => $e->status,
            ]);
            Cache::put($backoffKey, true, now()->addMinute());

            return;
        }

        if ($member === null) {
            $user->forceFill(['is_member' => false, 'is_admin' => false, 'roles_checked_at' => now()])->save();

            return;
        }

        $this->applyMember($user, $member);
        $user->save();
    }

    /**
     * @param  list<string>  $roleIds
     */
    public function isAdmin(string $discordId, array $roleIds): bool
    {
        $adminRoles = config('genetic.admin_role_ids', []);
        $adminUsers = config('genetic.admin_user_ids', []);

        return in_array($discordId, $adminUsers, true)
            || array_intersect($roleIds, $adminRoles) !== [];
    }

    /**
     * @param  array{nick?: string|null, roles: list<string>, user?: array<string, mixed>}  $member
     */
    private function applyMember(User $user, array $member): void
    {
        if (isset($member['user']['username'])) {
            $user->username = (string) $member['user']['username'];
            $user->global_name = $member['user']['global_name'] ?? null;
            $user->avatar_hash = $member['user']['avatar'] ?? null;
        }

        $user->guild_nickname = $member['nick'] ?? null;
        $user->display_name = $user->guild_nickname ?: ($user->global_name ?: $user->username);
        $user->is_member = true;
        $user->is_admin = $this->isAdmin($user->discord_id, $member['roles']);
        $user->roles_checked_at = now();
    }
}
