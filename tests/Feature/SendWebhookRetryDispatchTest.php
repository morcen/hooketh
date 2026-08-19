<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookRetryDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook', 'is_active' => true]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    public function test_http_failure_is_not_released_back_onto_the_queue(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        // The webhooks:process-retries scheduled command (driven by next_retry_at)
        // is the only mechanism that should re-dispatch a failed delivery. If the
        // job also releases itself back onto the queue, the same delivery is sent
        // to the customer's endpoint twice for a single logical retry.
        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_connection_exception_is_not_released_back_onto_the_queue(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not connect to host.');
        });

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_connection_exception_on_final_attempt_stops_retrying(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Could not connect to host.');
        });

        $maxAttempts = SendWebhook::maxAttempts();

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());
        $delivery->update([
            'status' => 'failed',
            // Simulate the delivery already being on its last allowed attempt.
            'attempt_count' => $maxAttempts - 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame($maxAttempts, $delivery->attempt_count);

        // Once retries are exhausted, next_retry_at must be cleared so the
        // exception path can't leave a stale, already-past timestamp behind
        // that scopeReadyForRetry() would keep matching forever, bypassing
        // WEBHOOK_MAX_RETRIES.
        $this->assertNull($delivery->next_retry_at);
        $this->assertSame(0, Delivery::readyForRetry()->whereKey($delivery->id)->count());
    }

    public function test_every_configured_backoff_delay_is_exercised_across_a_full_retry_cycle(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $backoffDelays = config('webhooks.backoff_delays', [60, 300, 900, 1800, 3600]);
        $maxAttempts = SendWebhook::maxAttempts();

        // maxAttempts is the initial attempt plus one retry per configured
        // backoff delay, so there should be exactly one delay per retry.
        $this->assertSame(count($backoffDelays), $maxAttempts - 1);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        foreach ($backoffDelays as $index => $expectedDelay) {
            $before = now();
            $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
            $job->handle();

            $delivery->refresh();
            $this->assertSame('failed', $delivery->status);
            $this->assertSame($index + 1, $delivery->attempt_count);
            $this->assertNotNull(
                $delivery->next_retry_at,
                "Retry {$index} should have been scheduled using backoff delay {$expectedDelay}s, but next_retry_at was cleared."
            );
            $this->assertEqualsWithDelta(
                $before->addSeconds($expectedDelay)->timestamp,
                $delivery->next_retry_at->timestamp,
                2
            );

            $delivery->update(['next_retry_at' => null]);
        }

        // One more failed attempt beyond the last configured delay exhausts
        // the delivery permanently.
        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $delivery->refresh();
        $this->assertSame($maxAttempts, $delivery->attempt_count);
        $this->assertNull($delivery->next_retry_at);
    }
}
