<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for #47: every prior test of
 * POST /api/v1/webhooks/trigger/{eventName} only asserted the controller's
 * own response (e.g. deliveries_created), never that the full pipeline
 * (trigger -> dispatched SendWebhook job -> delivered Delivery) actually
 * reaches a terminal status=success with delivered_at set. QUEUE_CONNECTION
 * is sync in tests, so SendWebhook runs inline and this can be asserted
 * end-to-end with no queue faking at all.
 */
class ApiWebhookTriggerSuccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_trigger_results_in_a_delivered_delivery(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['order_id' => 123],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliveries_created' => 1]);

        $delivery = Delivery::where('event_id', $event->id)->where('endpoint_id', $endpoint->id)->firstOrFail();

        $this->assertSame('success', $delivery->status);
        $this->assertSame(200, $delivery->response_code);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNull($delivery->next_retry_at);
        $this->assertSame(1, $delivery->attempt_count);
    }

    public function test_trigger_creates_one_delivery_per_active_endpoint_and_skips_inactive_ones(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.shipped']);
        $activeEndpoints = Endpoint::factory()->for($user)->count(2)->create(['is_active' => true]);
        $inactiveEndpoint = Endpoint::factory()->for($user)->create(['is_active' => false]);

        foreach ($activeEndpoints as $endpoint) {
            $event->endpoints()->attach($endpoint);
        }
        $event->endpoints()->attach($inactiveEndpoint);

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['order_id' => 456],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliveries_created' => 2]);

        $this->assertDatabaseCount('deliveries', 2);
        $this->assertSame(
            2,
            Delivery::where('event_id', $event->id)->where('status', 'success')->count()
        );
        $this->assertSame(
            0,
            Delivery::where('event_id', $event->id)->where('endpoint_id', $inactiveEndpoint->id)->count()
        );
    }
}
