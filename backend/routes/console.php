<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Algorithm 4 (No-show Detection): sweep for bookings whose check-in grace
// window has elapsed without a scan, and release the slot automatically.
Schedule::command('bookings:detect-no-shows')->everyMinute();
