<?php

namespace App\Console\Commands;

use App\Services\Maintenance;
use Illuminate\Console\Command;

/**
 * One maintenance run (Discord outbox, backup when due, restore test when
 * due). The scheduler calls it every minute; a plain system cron can call
 * `php artisan maintenance:tick` directly, shared hosting calls /ops/cron.
 */
class MaintenanceTick extends Command
{
    protected $signature = 'maintenance:tick';

    protected $description = 'Send the Discord outbox and run backups when they are due';

    public function handle(Maintenance $maintenance): int
    {
        $this->line(json_encode($maintenance->tick(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
