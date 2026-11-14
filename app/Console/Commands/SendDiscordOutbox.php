<?php

namespace App\Console\Commands;

use App\Services\Discord\OutboxSender;
use Illuminate\Console\Command;

/** Runs every minute from the scheduler (routes/console.php). */
class SendDiscordOutbox extends Command
{
    protected $signature = 'discord:send-outbox {--limit=20 : Rows per run}';

    protected $description = 'Send queued Discord announcements, edits and corrections';

    public function handle(OutboxSender $sender): int
    {
        $stats = $sender->run((int) $this->option('limit'));

        if (array_sum($stats) > 0) {
            $this->info(sprintf(
                'sent %d, retry later %d, failed %d, cancelled %d, skipped %d',
                $stats['sent'], $stats['retried'], $stats['failed'], $stats['cancelled'], $stats['skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
