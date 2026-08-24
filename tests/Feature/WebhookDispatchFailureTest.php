<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Covers a transient failure while queueing SendWebhook itself (e.g. the
 * "webhooks" queue connection is briefly unreachable), as opposed to the
 * webhook HTTP delivery failing. Before this fix, an exception here
 * propagated out of trigger() as an uncaught 500, aborted delivery creation
 * for any endpoints later in the loop, and left the Delivery permanently
 * stuck in "pending" — scopeReadyForRetry() only matches "failed" rows, so
 * nothing would ever pick it back up.
 */
class WebhookDispatchFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_trigger_marks_delivery_failed_and_retryable_when_dispatch_throws(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        Queue::shouldReceive('connection')
            ->andThrow(new RuntimeException('Queue connection unavailable'));

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['field' => 'value'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliveries_created' => 1]);

        $this->assertDatabaseCount('deliveries', 1);

        $delivery = Delivery::first();
        $this->assertSame('failed', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);
        $this->assertStringContainsString('Queue connection unavailable', $delivery->response_body);

        Http::assertNothingSent();
    }

    public function test_api_trigger_still_creates_deliveries_for_remaining_endpoints_when_one_dispatch_throws(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpointOne = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $endpointTwo = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach([$endpointOne->id, $endpointTwo->id]);

        Queue::shouldReceive('connection')
            ->andThrow(new RuntimeException('Queue connection unavailable'));

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['field' => 'value'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliveries_created' => 2]);
        $this->assertDatabaseCount('deliveries', 2);
        $this->assertSame(0, Delivery::where('status', 'pending')->count());
        $this->assertSame(2, Delivery::where('status', 'failed')->count());
    }

    public function test_dashboard_trigger_marks_delivery_failed_and_retryable_when_dispatch_throws(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        Queue::shouldReceive('connection')
            ->andThrow(new RuntimeException('Queue connection unavailable'));

        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['field' => 'value'],
        ]);

        $response->assertRedirect(route('events'));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('deliveries', 1);

        $delivery = Delivery::first();
        $this->assertSame('failed', $delivery->status);
        $this->assertNotNull($delivery->next_retry_at);

        Http::assertNothingSent();
    }
}
