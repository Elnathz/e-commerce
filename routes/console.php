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

// Sprint 9: Promotion reconciliation (nightly)
Schedule::command('promotions:reconcile')->dailyAt('02:00');

// Sprint 9: Cleanup expired export jobs (daily)
Schedule::command('exports:cleanup')->dailyAt('03:00');

// Sprint 9: Prune old notification dispatches (monthly)
Schedule::command('notifications:prune-dispatches')->monthly();

// Sprint 10: Cleanup activity logs (daily)
Schedule::command('activitylogs:cleanup')->dailyAt('04:00');
