<?php

use Illuminate\Support\Facades\Schedule;

// Discord announcements, edits and corrections from the outbox (DC-4).
Schedule::command('discord:send-outbox')->everyMinute()->withoutOverlapping(5);
