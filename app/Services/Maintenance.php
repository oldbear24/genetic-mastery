<?php

namespace App\Services;

use App\Services\Discord\CommandSync;
use App\Services\Discord\OutboxSender;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Everything that has to happen regularly, run in-process (no proc_open,
 * which shared hosting such as WEDOS disables):
 *
 *  - send the Discord outbox (every tick),
 *  - re-register /genetic when the species catalogue changed,
 *  - back up when the newest snapshot is older than BACKUP_EVERY_HOURS,
 *  - run the restore test when the last one is older than 7 days.
 *
 * Intervals are "due since the last run", not clock times, because a cron
 * that only calls a URL every 5 minutes or every hour cannot hit 03:30.
 */
class Maintenance
{
    private const LOCK = 'maintenance:tick';

    private const LAST_VERIFY = 'maintenance:last-backup-verify';

    public function __construct(
        private readonly OutboxSender $outbox,
        private readonly BackupService $backups,
        private readonly CommandSync $commands,
    ) {}

    /** @return array<string, mixed> */
    public function tick(): array
    {
        $lock = Cache::lock(self::LOCK, 900);

        if (! $lock->get()) {
            return ['skipped' => 'another run is still in progress'];
        }

        try {
            $report = [
                'outbox' => $this->attempt('outbox', fn () => $this->outbox->run(50)),
                'discord_commands' => $this->attempt('discord_commands', fn () => $this->commands->run()),
            ];

            if ($this->backupDue()) {
                $report['backup'] = $this->attempt('backup', fn () => $this->backups->run());
            }

            if ($this->verifyDue()) {
                $report['backup_verify'] = $this->attempt('backup_verify', function () {
                    $result = $this->backups->verify();
                    Cache::forever(self::LAST_VERIFY, now()->getTimestamp());

                    if (! $result['ok']) {
                        Log::error('Backup verification failed.', $result);
                    }

                    return $result;
                });
            }

            return $report;
        } finally {
            $lock->release();
        }
    }

    public function backupDue(): bool
    {
        if (! config('genetic.backup.enabled')) {
            return false;
        }

        $latest = $this->backups->latestSnapshotTime();

        return $latest === null
            || $latest->lte(now()->subHours((int) config('genetic.backup.every_hours'))->addMinutes(5));
    }

    public function verifyDue(): bool
    {
        if (! config('genetic.backup.enabled') || $this->backups->latestSnapshotTime() === null) {
            return false;
        }

        $last = Cache::get(self::LAST_VERIFY);

        return $last === null || (int) $last <= now()->subDays((int) config('genetic.backup.verify_every_days'))->getTimestamp();
    }

    /**
     * One failing task must not stop the others.
     *
     * @param  callable(): mixed  $task
     */
    private function attempt(string $name, callable $task): mixed
    {
        try {
            return $task();
        } catch (Throwable $e) {
            Log::error("Maintenance task {$name} failed.", ['exception' => $e::class, 'message' => $e->getMessage()]);

            return ['error' => $e::class.': '.$e->getMessage()];
        }
    }
}
