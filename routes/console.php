<?php

use Illuminate\Support\Facades\Schedule;

// Discord announcements, edits and corrections from the outbox (DC-4).
Schedule::command('discord:send-outbox')->everyMinute()->withoutOverlapping(5);

// Daily backup and a weekly restore test.
Schedule::command('backup:run')->dailyAt('03:30')->withoutOverlapping(60);
Schedule::command('backup:verify')->weeklyOn(1, '04:30');
