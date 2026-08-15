<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('subscriptions:expire')->hourly();
Schedule::command('otp:poll')->everyMinute();
