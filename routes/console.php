<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:expire')->everyMinute();
// Backup poll if background watcher dies (cron * * * * * schedule:run).
Schedule::command('otp:poll')->everyMinute()->withoutOverlapping(2);
Schedule::command('otp:sync-stock')->everyMinute()->withoutOverlapping(2);
