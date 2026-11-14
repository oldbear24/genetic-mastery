<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Restore test of a snapshot (default: the newest one). */
class BackupVerify extends Command
{
    protected $signature = 'backup:verify {snapshot? : Path on the backup disk}';

    protected $description = 'Restore a snapshot into a scratch database and check it against the evidence mirror';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->verify($this->argument('snapshot'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Snapshot', 'Integrity', 'Milestones', 'Submissions', 'Images', 'Missing files'], [[
            $result['snapshot'], $result['integrity'], $result['milestones'], $result['submissions'], $result['images'], count($result['missing_files']),
        ]]);

        foreach ($result['missing_files'] as $path) {
            $this->warn("Missing in mirror: {$path}");
        }

        if (! $result['ok']) {
            Log::error('Backup verification failed.', $result);
            $this->error('Backup verification FAILED.');

            return self::FAILURE;
        }

        $this->info('Backup verification OK.');

        return self::SUCCESS;
    }
}
