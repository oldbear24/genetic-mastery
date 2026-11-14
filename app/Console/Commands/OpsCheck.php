<?php

namespace App\Console\Commands;

use App\Services\EnvironmentCheck;
use Illuminate\Console\Command;

/** The same environment check as /ops/deploy/check, for hosts with a shell. */
class OpsCheck extends Command
{
    protected $signature = 'ops:check {--no-discord : Skip the Discord bot token check}';

    protected $description = 'Check PHP, database, files, limits and configuration for production';

    public function handle(EnvironmentCheck $check): int
    {
        $results = $check->run(! $this->option('no-discord'));

        $this->table(['', 'Check', 'Detail'], array_map(fn (array $r) => [strtoupper($r['status']), $r['check'], $r['detail']], $results));

        return $check->passed($results) ? self::SUCCESS : self::FAILURE;
    }
}
