<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookPayloadSizeLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_trigger_rejects_payload_exceeding_configured_max_size(): void
    {
        config(['webhooks.payload_max_size' => 100]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['data' => str_repeat('a', 200)],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('payload');
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_api_trigger_accepts_payload_within_configured_max_size(): void
    {
        Http::fake();
        config(['webhooks.payload_max_size' => 100]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['deliveries_created' => 1]);
    }

    public function test_dashboard_trigger_rejects_payload_exceeding_configured_max_size(): void
    {
        config(['webhooks.payload_max_size' => 100]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['data' => str_repeat('a', 200)],
        ]);

        $response->assertSessionHasErrors('payload');
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_dashboard_trigger_accepts_payload_within_configured_max_size(): void
    {
        Http::fake();
        config(['webhooks.payload_max_size' => 100]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('deliveries', 1);
    }
}
