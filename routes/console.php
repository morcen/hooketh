<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule webhook retry processing every minute. withoutOverlapping() stops
// a second tick from starting while a prior run is still processing a large
// retry backlog (e.g. after an outage) — without it, both ticks could query
// for ready deliveries before either flips them out of status=failed and
// double-dispatch the same Delivery to the customer's endpoint.
Schedule::command('webhooks:process-retries')->everyMinute()->withoutOverlapping();

// Write a heartbeat so the /health endpoint can verify the scheduler is alive
Schedule::command('queue:heartbeat')->everyMinute();
