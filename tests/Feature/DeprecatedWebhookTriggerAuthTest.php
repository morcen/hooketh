<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['event' => $event->name, 'deliveries_created' => 1]);
    }
}
