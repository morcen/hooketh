<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class QueueHeartbeat extends Command
{
    protected $signature = 'queue:heartbeat';

    protected $description = 'Write a heartbeat timestamp to Redis so the health check can verify the scheduler is running';

    public function handle(): void
    {
        Redis::set('queue:heartbeat', now()->timestamp);
    }
}
