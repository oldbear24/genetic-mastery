<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * A player or admin, identified by the Discord ID. Names and avatar are
 * refreshed on every login, history always points at the ID.
 *
 * @property int $id
 * @property string $discord_id
 * @property string $username
 * @property string|null $global_name
 * @property string|null $guild_nickname
 * @property string $display_name
 * @property string|null $avatar_hash
 * @property bool $is_admin
 * @property bool $is_member
 * @property Carbon|null $roles_checked_at
 * @property Carbon|null $last_login_at
 */
#[Fillable([
    'discord_id', 'username', 'global_name', 'guild_nickname', 'display_name',
    'avatar_hash', 'is_admin', 'is_member', 'roles_checked_at', 'last_login_at',
])]
#[Hidden(['remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_member' => 'boolean',
            'roles_checked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /** Users sign in only through Discord; there is no password. */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->is_admin && $this->is_member;
    }

    public function avatarUrl(int $size = 64): ?string
    {
        if ($this->avatar_hash === null) {
            return null;
        }

        return "https://cdn.discordapp.com/avatars/{$this->discord_id}/{$this->avatar_hash}.png?size={$size}";
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->display_name, 0, 1));
    }
}
