<?php

namespace Tests\Feature;

use App\Jobs\WriteQueueHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Regression coverage for #108: /health/detailed's queue_worker status used
 * to reflect only the scheduler's own liveness (the queue:heartbeat command
 * wrote the Redis heartbeat key directly), so a dead queue worker sitting
 * alongside a healthy scheduler container was reported as "ok" — a false
 * positive that hides silently-stuck webhook deliveries. The heartbeat is
 * now written by a job dispatched onto the same `webhooks` queue SendWebhook
 * runs on, so the key only updates once an actual worker processes it.
 */
class QueueHeartbeatTest extends TestCase
{
    public function test_queue_heartbeat_command_dispatches_a_job_onto_the_webhooks_queue(): void
    {
        Queue::fake();

        Artisan::call('queue:heartbeat');

        Queue::assertPushed(
            WriteQueueHeartbeat::class,
            fn (WriteQueueHeartbeat $job) => $job->queue === 'webhooks'
        );
    }

    public function test_queue_heartbeat_command_does_not_write_the_heartbeat_key_itself(): void
    {
        // The command only enqueues the job; if it wrote the Redis key
        // directly, the heartbeat would stay "fresh" even while the actual
        // queue worker process is dead, reproducing the original bug.
        Queue::fake();
        Redis::shouldReceive('set')->never();

        Artisan::call('queue:heartbeat');
    }

    public function test_write_queue_heartbeat_job_writes_the_current_timestamp_to_redis(): void
    {
        $now = now();
        $this->travelTo($now);

        Redis::shouldReceive('set')
            ->once()
            ->with('queue:heartbeat', $now->timestamp);

        (new WriteQueueHeartbeat)->handle();
    }

    public function test_write_queue_heartbeat_job_runs_on_the_webhooks_queue(): void
    {
        // SendWebhook also runs on the `webhooks` queue, so a worker that
        // has stopped processing deliveries also stops processing this job
        // — which is exactly what makes the heartbeat a real liveness
        // signal for the worker that matters.
        $job = new WriteQueueHeartbeat;

        $this->assertSame('webhooks', $job->queue);
    }
}
