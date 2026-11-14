<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupRun extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Database snapshot and evidence mirror on the backup disk, 30 day retention';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->run();
        } catch (Throwable $e) {
            Log::error('Backup failed.', ['exception' => $e::class, 'message' => $e->getMessage()]);
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        Log::info('Backup done.', $result);
        $this->info("Snapshot: {$result['snapshot']}");
        $this->info("Evidence files copied: {$result['evidence_copied']}, old snapshots removed: {$result['pruned']}");

        return self::SUCCESS;
    }
}
