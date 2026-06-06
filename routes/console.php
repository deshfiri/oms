<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync mirrors every 5 minutes
Schedule::command('dfoms:sync')->everyFiveMinutes()->withoutOverlapping();

// Process queue (alternative: run a real worker; the scheduler-driven dispatcher
// keeps the dev environment alive without a separate process)
Schedule::command('queue:work --stop-when-empty --queue=webhooks,sync,default')
    ->everyMinute()->withoutOverlapping();
