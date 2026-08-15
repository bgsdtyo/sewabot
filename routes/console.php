<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:expire')->hourly();
// Backup poll if background watcher dies (cron * * * * * schedule:run).
Schedule::command('otp:poll')->everyMinute()->withoutOverlapping();
