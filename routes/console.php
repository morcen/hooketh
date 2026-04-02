<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule webhook retry processing every minute
Schedule::command('webhooks:process-retries')->everyMinute();

// Write a heartbeat so the /health endpoint can verify the scheduler is alive
Schedule::command('queue:heartbeat')->everyMinute();
