<?php

namespace App\Console\Commands;

use App\Jobs\WriteQueueHeartbeat;
use Illuminate\Console\Command;

class QueueHeartbeat extends Command
{
    protected $signature = 'queue:heartbeat';

    protected $description = 'Dispatch a job onto the webhooks queue so the health check can verify a live queue worker (not just the scheduler) is running';

    public function handle(): void
    {
        // Dispatched rather than written directly: the heartbeat key must
        // only update once an actual queue worker processes this job, so
        // staleness reflects worker liveness instead of just the scheduler
        // (which runs this command) being alive. See WriteQueueHeartbeat.
        WriteQueueHeartbeat::dispatch();
    }
}
