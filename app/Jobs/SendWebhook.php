<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Models\Endpoint;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhook implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Delivery $delivery
    ) {
        $this->onQueue('webhooks');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->delivery->increment('attempt_count');
        $this->delivery->update(['status' => 'retrying']);

        $endpoint = $this->delivery->endpoint;
        $payload = $this->delivery->payload;

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Secret' => hash_hmac('sha256', json_encode($payload), $endpoint->secret_key),
                    'X-Webhook-Event' => $this->delivery->event->name,
                    'User-Agent' => 'Webhook-Management-Platform/1.0',
                ])
                ->post($endpoint->url, $payload);

            $this->delivery->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => $response->successful() ? now() : null,
                'next_retry_at' => null,
            ]);

            if ($response->successful()) {
                Log::info('Webhook delivered successfully', [
                    'delivery_id' => $this->delivery->id,
                    'endpoint_url' => $endpoint->url,
                    'response_code' => $response->status(),
                ]);
            } else {
                Log::warning('Webhook delivery failed', [
                    'delivery_id' => $this->delivery->id,
                    'endpoint_url' => $endpoint->url,
                    'response_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                $this->handleFailedDelivery();
            }
        } catch (Exception $e) {
            Log::error('Webhook delivery exception', [
                'delivery_id' => $this->delivery->id,
                'endpoint_url' => $endpoint->url,
                'error' => $e->getMessage(),
            ]);

            $this->delivery->update([
                'status' => 'failed',
                'response_code' => null,
                'response_body' => $e->getMessage(),
                'delivered_at' => null,
            ]);

            $this->handleFailedDelivery();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        $this->delivery->update([
            'status' => 'failed',
            'response_body' => $exception->getMessage(),
            'next_retry_at' => null,
        ]);

        Log::error('Webhook job failed permanently', [
            'delivery_id' => $this->delivery->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        // Exponential backoff: 1min, 5min, 15min, 30min, 1hour
        return [60, 300, 900, 1800, 3600];
    }

    private function handleFailedDelivery(): void
    {
        if ($this->delivery->attempt_count < $this->tries) {
            $delay = $this->backoff()[$this->delivery->attempt_count - 1] ?? 3600;
            $this->delivery->update([
                'next_retry_at' => now()->addSeconds($delay),
            ]);

            // Retry the job
            $this->release($delay);
        }
    }
}
