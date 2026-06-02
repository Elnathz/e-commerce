<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR016: Auto-cancel expired orders every 15 minutes
Schedule::command('orders:cancel-expired')->everyFifteenMinutes();

// FR028: Auto-cancel stale orders (stuck > 7 days) daily
Schedule::command('orders:auto-cancel-stale')->daily();
