<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('currency:fetch-rates')->dailyAt('03:00')->withoutOverlapping();

// Supplier stock (OnePrice …): follow the fresh exchange rate, then weekly
// housekeeping of long-delisted vehicles and old import working data.
Schedule::command('suppliers:reprice')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('suppliers:purge')->weeklyOn(0, '04:00')->withoutOverlapping();

// Queue worker, run by the scheduler (no supervisor on this host). Serves the
// TOCO Mailer jobs first, then anything else on the default queue.
Schedule::command('queue:work --queue=mailer,default --stop-when-empty --max-time=55 --tries=3 --timeout=50')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();
