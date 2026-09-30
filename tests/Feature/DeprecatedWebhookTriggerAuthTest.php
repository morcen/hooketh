<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DeprecatedWebhookTriggerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_deprecated_trigger_route_rejects_unauthenticated_requests(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);

        $response = $this->postJson('/api/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(401);
    }

    public function test_deprecated_trigger_route_works_for_authenticated_users(): void
    {
        Bus::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['event' => $event->name, 'deliveries_created' => 1]);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseHas('deliveries', [
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
        ]);
        Bus::assertDispatched(SendWebhook::class);
    }
}
