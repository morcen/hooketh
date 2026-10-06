<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for #47: the HMAC signature sent in the
 * X-Webhook-Secret header is the single mechanism a webhook consumer relies
 * on to verify a delivery actually came from this platform, but until now
 * no test anywhere asserted its value. A regression here (wrong algorithm,
 * wrong payload serialization, or signing with the wrong secret) would
 * silently break signature verification for every customer's webhook
 * consumer without failing any existing test.
 */
class SendWebhookHmacSigningTest extends TestCase
{
    use RefreshDatabase;

    public function test_x_webhook_secret_header_is_a_valid_hmac_of_the_payload(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
            'payload' => ['foo' => 'bar', 'nested' => ['baz' => 1]],
        ]);

        (new SendWebhook($delivery))->handle();

        Http::assertSent(function (ClientRequest $request) use ($delivery, $endpoint) {
            $expectedSignature = hash_hmac('sha256', json_encode($delivery->payload), $endpoint->secret_key);

            return $request->hasHeader('X-Webhook-Secret', $expectedSignature);
        });
    }

    public function test_x_webhook_secret_header_differs_per_endpoint_secret(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpointA = Endpoint::factory()->for($user)->create(['is_active' => true, 'secret_key' => 'secret-a']);
        $endpointB = Endpoint::factory()->for($user)->create(['is_active' => true, 'secret_key' => 'secret-b']);

        $payload = ['event' => 'order.created'];

        $deliveryA = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpointA->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
            'payload' => $payload,
        ]);
        $deliveryB = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpointB->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
            'payload' => $payload,
        ]);

        (new SendWebhook($deliveryA))->handle();
        (new SendWebhook($deliveryB))->handle();

        $signatureA = hash_hmac('sha256', json_encode($payload), 'secret-a');
        $signatureB = hash_hmac('sha256', json_encode($payload), 'secret-b');

        $this->assertNotSame($signatureA, $signatureB);

        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('X-Webhook-Secret', $signatureA));
        Http::assertSent(fn (ClientRequest $request) => $request->hasHeader('X-Webhook-Secret', $signatureB));
    }
}
