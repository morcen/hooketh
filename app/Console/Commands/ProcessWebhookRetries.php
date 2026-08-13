<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use Illuminate\Console\Command;

class ProcessWebhookRetries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhooks:process-retries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process webhook delivery retries that are ready to be retried';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->recoverStuckRetries();

        $this->info('Processing webhook delivery retries...');

        $retriesQuery = Delivery::readyForRetry();
        $retriesCount = $retriesQuery->count();

        if ($retriesCount === 0) {
            $this->info('No deliveries ready for retry.');

            return Command::SUCCESS;
        }

        $this->info("Found {$retriesCount} deliveries ready for retry.");

        $retries = $retriesQuery->get();

        foreach ($retries as $delivery) {
            $delivery->update([
                'status' => 'pending',
                'next_retry_at' => null,
            ]);

            SendWebhook::dispatch($delivery);

            $this->line("Queued retry for delivery {$delivery->id}");
        }

        $this->info("Successfully queued {$retriesCount} delivery retries.");

        return Command::SUCCESS;
    }

    /**
     * Recover deliveries abandoned mid-attempt (queue worker crash/restart)
     * that were left in `retrying` status. That status is invisible to every
     * other status-based query, including scopeReadyForRetry(), so without
     * this sweep those rows would never be retried again.
     */
    private function recoverStuckRetries(): void
    {
        $stuck = Delivery::stuckRetrying()->get();

        if ($stuck->isEmpty()) {
            return;
        }

        $maxRetries = config('webhooks.max_retries', 5);

        foreach ($stuck as $delivery) {
            if ($delivery->attempt_count >= $maxRetries) {
                $delivery->update([
                    'status' => 'failed',
                    'response_body' => 'Delivery abandoned mid-attempt (queue worker crash or restart) after exhausting all retry attempts.',
                    'next_retry_at' => null,
                ]);

                $this->line("Delivery {$delivery->id} was stuck retrying and exhausted its attempts; marked permanently failed.");

                continue;
            }

            $delivery->update([
                'status' => 'failed',
                'response_body' => 'Delivery abandoned mid-attempt (queue worker crash or restart); re-queued for retry.',
                'next_retry_at' => now(),
            ]);

            $this->line("Delivery {$delivery->id} was stuck retrying; re-queued for retry.");
        }
    }
}
