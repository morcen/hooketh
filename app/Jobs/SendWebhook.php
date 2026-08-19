<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Rules\SafeWebhookUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $maxExceptions = 3;

    public function tries(): int
    {
        // config('webhooks.max_retries') is the number of *retries*, not the
        // total attempt count — the initial attempt is on top of that. Adding
        // 1 here (rather than treating max_retries as the total attempt
        // ceiling) is what lets handleFailedDelivery() actually reach and use
        // the last configured backoff delay instead of stopping one attempt
        // short of it.
        return self::maxAttempts();
    }

    /**
     * Total attempts allowed for a delivery: the initial attempt plus every
     * configured retry. Shared by handleFailedDelivery() (the normal retry
     * path) and ProcessWebhookRetries::recoverStuckRetries() (the crashed
     * worker recovery path) so both agree on when a delivery is exhausted.
     */
    public static function maxAttempts(): int
    {
        return config('webhooks.max_retries', 5) + 1;
    }

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

        if (! $endpoint || $endpoint->trashed()) {
            $this->delivery->update(['status' => 'failed', 'response_body' => 'Endpoint was deleted.']);

            return;
        }

        $safeIp = SafeWebhookUrl::resolveSafeIp($endpoint->url);

        if ($safeIp === null) {
            Log::warning('Webhook delivery blocked: endpoint URL resolves to a disallowed network address', [
                'delivery_id' => $this->delivery->id,
                'endpoint_url' => $endpoint->url,
            ]);

            $this->delivery->update([
                'status' => 'failed',
                'response_code' => null,
                'response_body' => 'Delivery blocked: endpoint URL resolves to a private, loopback, or reserved network address.',
                'delivered_at' => null,
            ]);

            $this->handleFailedDelivery();

            return;
        }

        $payload = $this->delivery->payload;

        // Pin the connection to the IP address that was just validated,
        // rather than letting curl re-resolve the hostname at request time.
        // Without this, an attacker controlling DNS for the endpoint's
        // domain could return a safe IP for validation and a private/
        // internal one moments later for the actual connection.
        $urlParts = parse_url($endpoint->url);
        $host = trim($urlParts['host'] ?? '', '[]');
        $port = $urlParts['port'] ?? (strtolower($urlParts['scheme'] ?? '') === 'https' ? 443 : 80);
        $maxResponseBytes = (int) config('webhooks.response_body_max_size', 65536);

        try {
            $start = microtime(true);
            $response = Http::timeout(30)
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [
                        CURLOPT_RESOLVE => ["{$host}:{$port}:{$safeIp}"],
                        // Abort the transfer early if the endpoint tries to stream
                        // back more than the configured cap, rather than buffering
                        // an arbitrarily large body in memory.
                        CURLOPT_NOPROGRESS => false,
                        CURLOPT_PROGRESSFUNCTION => fn ($resource, $downloadSize, $downloaded) => $downloaded > $maxResponseBytes ? 1 : 0,
                    ],
                ])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Secret' => hash_hmac('sha256', json_encode($payload), $endpoint->secret_key),
                    'X-Webhook-Event' => $this->delivery->event->name,
                    'User-Agent' => 'Webhook-Management-Platform/1.0',
                ])
                ->post($endpoint->url, $payload);
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $responseBody = $this->truncateResponseBody($response->body(), $maxResponseBytes);

            $this->delivery->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => $responseBody,
                'duration_ms' => $durationMs,
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
                    'response_body' => $responseBody,
                ]);

                $this->handleFailedDelivery();
            }
        } catch (Throwable $e) {
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
                'next_retry_at' => null,
            ]);

            $this->handleFailedDelivery();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
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
        return config('webhooks.backoff_delays', [60, 300, 900, 1800, 3600]);
    }

    private function truncateResponseBody(string $body, int $maxBytes): string
    {
        if (strlen($body) <= $maxBytes) {
            return $body;
        }

        return substr($body, 0, $maxBytes)."\n...[truncated, response body exceeded {$maxBytes} bytes]";
    }

    private function handleFailedDelivery(): void
    {
        if ($this->delivery->attempt_count < $this->tries()) {
            $delay = $this->backoff()[$this->delivery->attempt_count - 1] ?? 3600;
            $this->delivery->update([
                'next_retry_at' => now()->addSeconds($delay),
            ]);

            // Retries are re-dispatched exclusively by the webhooks:process-retries
            // scheduled command (driven by next_retry_at). Also releasing this job
            // instance back onto the queue here would cause the same delivery to be
            // sent twice: once when this job's release() delay elapses, and again
            // when the cron tick finds the same row via Delivery::readyForRetry().
        }
    }
}
