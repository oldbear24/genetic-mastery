<?php

namespace App\Services;

use App\Models\Milestone;
use App\Services\Discord\DiscordClient;
use App\Services\Discord\DiscordUnavailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Checks the hosting environment the way the app needs it. Used by
 * /ops/deploy/check on hosts without SSH (WEDOS webhosting) and by
 * `php artisan ops:check`. Never prints secret values.
 */
class EnvironmentCheck
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** @return list<array{status: string, check: string, detail: string}> */
    public function run(bool $withDiscord = true): array
    {
        $results = [];
        $add = function (string $status, string $check, string $detail = '') use (&$results) {
            $results[] = ['status' => $status, 'check' => $check, 'detail' => $detail];
        };

        // PHP and extensions
        $add(version_compare(PHP_VERSION, '8.4.0', '>=') ? self::OK : self::FAIL, 'PHP >= 8.4', PHP_VERSION);
        foreach (['pdo', 'mbstring', 'fileinfo', 'gd', 'sodium', 'openssl', 'intl', 'zlib'] as $ext) {
            $add(extension_loaded($ext) ? self::OK : self::FAIL, "ext-{$ext}");
        }
        $gd = function_exists('gd_info') ? gd_info() : [];
        foreach (['PNG Support', 'JPEG Support', 'WebP Support'] as $feature) {
            $add(! empty($gd[$feature]) ? self::OK : self::FAIL, "GD {$feature}");
        }
        $add(function_exists('fastcgi_finish_request') ? self::OK : self::WARN, 'fastcgi_finish_request',
            'needed for DISCORD_SEND_AFTER_RESPONSE to not delay the admin');

        // Database
        try {
            $driver = DB::connection()->getDriverName();
            DB::connection()->getPdo();
            $add(self::OK, 'database connection', $driver);
            $add(extension_loaded($driver === 'sqlite' ? 'pdo_sqlite' : ($driver === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql')) ? self::OK : self::FAIL, "PDO driver for {$driver}");

            $migrator = app('migrator');
            $ran = $migrator->getRepository()->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $ran);
            $add($pending === [] ? self::OK : self::FAIL, 'migrations', $pending === [] ? 'all ran' : count($pending).' pending: run /ops/deploy/migrate');

            if ($pending === []) {
                $count = Milestone::query()->count();
                $add($count === 54 ? self::OK : self::FAIL, 'milestone catalogue', "{$count} milestones");
            }

            if ($driver === 'sqlite') {
                $mode = (string) DB::selectOne('PRAGMA journal_mode')->journal_mode;
                $add(self::WARN, 'SQLite journal mode', "{$mode}; on network storage of shared hosting prefer MariaDB, or DB_JOURNAL_MODE=delete");
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $triggers = count(DB::select("SHOW TRIGGERS LIKE 'audit_log'"));
                $add($triggers >= 2 ? self::OK : self::WARN, 'audit_log triggers', $triggers >= 2 ? 'present' : 'missing (no TRIGGER privilege); the model still blocks changes');
            }
        } catch (Throwable $e) {
            $add(self::FAIL, 'database connection', $e::class.': '.mb_substr($e->getMessage(), 0, 200));
        }

        // Files
        foreach (['storage/app/private', 'storage/app/tmp', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $path = base_path($dir);
            File::ensureDirectoryExists($path);
            $add(is_writable($path) ? self::OK : self::FAIL, "writable {$dir}");
        }
        $add(is_file(public_path('build/manifest.json')) ? self::OK : self::FAIL, 'frontend build', public_path('build/manifest.json'));
        $add(str_starts_with(realpath(storage_path()) ?: '', realpath(public_path()) ?: "\0") ? self::FAIL : self::OK, 'storage outside the web root');

        // Limits
        $maxMb = (int) config('genetic.upload.max_mb');
        $totalMb = (int) config('genetic.upload.max_total_mb');
        $files = (int) config('genetic.upload.max_files');
        $upload = $this->bytes((string) ini_get('upload_max_filesize'));
        $post = $this->bytes((string) ini_get('post_max_size'));
        $add($upload > $maxMb * 1048576 ? self::OK : self::FAIL, 'upload_max_filesize', ini_get('upload_max_filesize')." (app limit {$maxMb} MB, PHP must allow a bit more)");
        $add($post > $totalMb * 1048576 ? self::OK : self::FAIL, 'post_max_size', ini_get('post_max_size')." (app limit {$totalMb} MB)");
        $add((int) ini_get('max_file_uploads') > $files ? self::OK : self::FAIL, 'max_file_uploads', ini_get('max_file_uploads')." (must be more than {$files} so the 11th file is refused by the app)");
        $memory = $this->bytes((string) ini_get('memory_limit'));
        $needed = (int) (config('genetic.upload.max_megapixels') * 1_000_000 * 5 * 1.5) + 64 * 1048576;
        $add($memory === -1 || $memory >= $needed ? self::OK : self::WARN, 'memory_limit', ini_get('memory_limit').' (about '.round($needed / 1048576).' MB for '.config('genetic.upload.max_megapixels').' Mpx images)');
        $add(function_exists('proc_open') ? self::OK : self::WARN, 'proc_open', function_exists('proc_open') ? 'available' : 'disabled: use /ops/cron instead of schedule:run with commands');
        $add(self::OK, 'open_basedir', ini_get('open_basedir') ?: 'none');

        // Configuration
        $add(filled(config('app.key')) ? self::OK : self::FAIL, 'APP_KEY');
        $add(app()->isProduction() ? self::OK : self::WARN, 'APP_ENV', (string) config('app.env'));
        $add(config('app.debug') ? (app()->isProduction() ? self::FAIL : self::WARN) : self::OK, 'APP_DEBUG off', config('app.debug') ? 'on' : 'off');
        $add(str_starts_with((string) config('app.url'), 'https://') ? self::OK : self::WARN, 'APP_URL uses https', (string) config('app.url'));
        $add(request()->isSecure() ? self::OK : self::WARN, 'request seen as HTTPS', request()->isSecure() ? 'yes' : 'no: set TRUSTED_PROXIES=* behind a proxy');
        foreach (['client_id', 'client_secret', 'bot_token', 'public_key', 'guild_id', 'announce_channel_id'] as $key) {
            $add(filled(config("services.discord.{$key}")) ? self::OK : self::FAIL, 'DISCORD_'.strtoupper($key), filled(config("services.discord.{$key}")) ? 'set' : 'missing');
        }
        $publicKey = (string) config('services.discord.public_key');
        $add(ctype_xdigit($publicKey) && strlen($publicKey) === 64 ? self::OK : self::FAIL, 'DISCORD_PUBLIC_KEY format', '64 hex characters');
        $add(filled(config('genetic.admin_role_ids')) || filled(config('genetic.admin_user_ids')) ? self::OK : self::WARN, 'ADMIN_ROLE_IDS or ADMIN_USER_IDS');

        if ($withDiscord && filled(config('services.discord.bot_token'))) {
            try {
                $bot = app(DiscordClient::class)->currentUser();
                $add(self::OK, 'Discord bot token', 'bot '.($bot['username'] ?? '?'));
            } catch (DiscordUnavailable $e) {
                $add(self::FAIL, 'Discord bot token', 'HTTP '.($e->status ?? '-'));
            }
        }

        return $results;
    }

    public function passed(array $results): bool
    {
        return collect($results)->doesntContain('status', self::FAIL);
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
