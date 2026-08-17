<?php

namespace App\Console\Commands;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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

        // Claiming (select + status flip) happens inside a single transaction
        // with row-level locking so that even a manual duplicate invocation
        // of this command can't pick up the same Delivery row twice. This is
        // the atomic-claim half of the fix; withoutOverlapping() on the
        // scheduled command (routes/console.php) is the other half, stopping
        // a second scheduler tick from starting at all while a prior run is
        // still processing a backlog.
        $retries = DB::transaction(function () {
            $claimed = Delivery::readyForRetry()->lockForUpdate()->get();

            foreach ($claimed as $delivery) {
                $delivery->update([
                    'status' => 'pending',
                    'next_retry_at' => null,
                ]);
            }

            return $claimed;
        });

        if ($retries->isEmpty()) {
            $this->info('No deliveries ready for retry.');

            return Command::SUCCESS;
        }

        $retriesCount = $retries->count();
        $this->info("Found {$retriesCount} deliveries ready for retry.");

        foreach ($retries as $delivery) {
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

        $maxAttempts = SendWebhook::maxAttempts();

        foreach ($stuck as $delivery) {
            if ($delivery->attempt_count >= $maxAttempts) {
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
