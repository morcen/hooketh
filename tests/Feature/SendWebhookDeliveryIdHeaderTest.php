<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookDeliveryIdHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $user, array $attributes = []): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook', 'is_active' => true]);

        return Delivery::factory()->create(array_merge([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ], $attributes));
    }

    public function test_delivery_id_and_attempt_headers_are_sent_with_the_request(): void
    {
        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $delivery->refresh();

        Http::assertSent(function ($request) use ($delivery) {
            return $request->hasHeader('X-Webhook-Delivery-Id', (string) $delivery->id)
                && $request->hasHeader('X-Webhook-Attempt', (string) $delivery->attempt_count);
        });
    }

    public function test_delivery_id_stays_stable_while_attempt_number_increments_across_retries(): void
    {
        Http::fake([
            '*' => Http::response('error', 500),
        ]);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());
        $deliveryId = (string) $delivery->id;

        // First attempt.
        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        Http::assertSent(function ($request) use ($deliveryId) {
            return $request->hasHeader('X-Webhook-Delivery-Id', $deliveryId)
                && $request->hasHeader('X-Webhook-Attempt', '1');
        });

        // Simulate the scheduled retry re-dispatch.
        $delivery->refresh();
        $delivery->update(['next_retry_at' => null]);
        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        Http::assertSent(function ($request) use ($deliveryId) {
            return $request->hasHeader('X-Webhook-Delivery-Id', $deliveryId)
                && $request->hasHeader('X-Webhook-Attempt', '2');
        });
    }
}
