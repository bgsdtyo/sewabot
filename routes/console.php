<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:expire')->everyMinute()->runInBackground();
// Backup poll if background watcher dies (cron * * * * * schedule:run).
Schedule::command('otp:poll')->everyMinute()->runInBackground()->withoutOverlapping(2);
Schedule::command('otp:sync-stock')->everyMinute()->runInBackground()->withoutOverlapping(2);
