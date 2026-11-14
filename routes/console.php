<?php

use App\Services\Maintenance;
use Illuminate\Support\Facades\Schedule;

// One in-process maintenance run per minute: Discord outbox (DC-4), daily
// backup and weekly restore test when they are due. A closure instead of
// Schedule::command() so nothing needs proc_open. On shared hosting without
// a CLI cron the same run is triggered by /ops/cron/{token}.
Schedule::call(fn () => app(Maintenance::class)->tick())
    ->name('maintenance')
    ->everyMinute();
