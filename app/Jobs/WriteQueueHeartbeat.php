<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

/**
 * Writes the `queue:heartbeat` Redis key that /health/detailed uses to
 * report queue_worker status. Unlike a plain scheduler-run command, this
 * only happens once a live queue worker actually pulls the job off the
 * `webhooks` queue and executes it — the same queue (and worker process)
 * SendWebhook runs on. If that worker dies while the scheduler container
 * keeps ticking, this job sits unprocessed, the heartbeat key goes stale,
 * and /health/detailed correctly reports it — instead of the scheduler's
 * own liveness being mistaken for the worker's (see #108).
 */
class WriteQueueHeartbeat implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        Redis::set('queue:heartbeat', now()->timestamp);
    }
}
