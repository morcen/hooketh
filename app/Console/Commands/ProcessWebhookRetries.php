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
}
