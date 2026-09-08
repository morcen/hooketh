<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression tests for issue #53: `auth:sanctum` alone only verifies that a
 * token is valid, not what it is scoped to do. A personal access token
 * restricted to "read" in the API Tokens UI used to retain full
 * create/update/delete access anyway, because no controller ever called
 * tokenCan()/checked the token's abilities.
 */
class ApiTokenAbilityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function endpointFor(User $user): Endpoint
    {
        return Endpoint::factory()->for($user)->create(['is_active' => true]);
    }

    private function eventFor(User $user): Event
    {
        return Event::factory()->for($user)->create();
    }

    private function deliveryFor(User $user): Delivery
    {
        $event = $this->eventFor($user);
        $endpoint = $this->endpointFor($user);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    // -- Endpoints ---------------------------------------------------------

    public function test_read_only_token_cannot_create_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson('/api/v1/endpoints', [
            'name' => 'Hijacked',
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('endpoints', ['name' => 'Hijacked']);
    }

    public function test_read_only_token_cannot_update_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->endpointFor($user);
        Sanctum::actingAs($user, ['read']);

        $response = $this->putJson("/api/v1/endpoints/{$endpoint->id}", ['name' => 'Hijacked']);

        $response->assertStatus(403);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'name' => $endpoint->name]);
    }

    public function test_read_only_token_cannot_delete_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->endpointFor($user);
        Sanctum::actingAs($user, ['read']);

        $response = $this->deleteJson("/api/v1/endpoints/{$endpoint->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'deleted_at' => null]);
    }

    public function test_read_only_token_cannot_regenerate_endpoint_secret(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->endpointFor($user);
        $originalSecret = $endpoint->secret_key;
        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson("/api/v1/endpoints/{$endpoint->id}/regenerate-secret");

        $response->assertStatus(403);
        $this->assertSame($originalSecret, $endpoint->fresh()->secret_key);
    }

    public function test_read_ability_token_can_list_and_view_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->endpointFor($user);
        Sanctum::actingAs($user, ['read']);

        $this->getJson('/api/v1/endpoints')->assertOk();
        $this->getJson("/api/v1/endpoints/{$endpoint->id}")->assertOk();
    }

    public function test_create_ability_token_can_create_endpoint(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user, ['create']);

        $response = $this->postJson('/api/v1/endpoints', [
            'name' => 'Allowed',
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('endpoints', ['name' => 'Allowed', 'user_id' => $user->id]);
    }

    public function test_token_without_read_ability_cannot_list_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user, ['create', 'update', 'delete']);

        $response = $this->getJson('/api/v1/endpoints');

        $response->assertStatus(403);
    }

    // -- Events --------------------------------------------------------------

    public function test_read_only_token_cannot_create_event(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson('/api/v1/events', ['name' => 'hijacked.event']);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('events', ['name' => 'hijacked.event']);
    }

    public function test_read_only_token_cannot_update_event(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->eventFor($user);
        Sanctum::actingAs($user, ['read']);

        $response = $this->putJson("/api/v1/events/{$event->id}", ['name' => 'hijacked.event']);

        $response->assertStatus(403);
    }

    public function test_read_only_token_cannot_delete_event(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->eventFor($user);
        Sanctum::actingAs($user, ['read']);

        $response = $this->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    // -- Deliveries ------------------------------------------------------------

    public function test_token_without_read_ability_cannot_list_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->deliveryFor($user);
        Sanctum::actingAs($user, ['create']);

        $response = $this->getJson('/api/v1/deliveries');

        $response->assertStatus(403);
    }

    public function test_token_without_read_ability_cannot_view_delivery_stats(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Sanctum::actingAs($user, ['create']);

        $response = $this->getJson('/api/v1/deliveries/stats');

        $response->assertStatus(403);
    }

    // -- Webhook trigger / retry ------------------------------------------------

    public function test_read_only_token_cannot_trigger_webhook(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->eventFor($user);
        $endpoint = $this->endpointFor($user);
        $event->endpoints()->attach($endpoint);
        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('deliveries', ['event_id' => $event->id]);
    }

    public function test_create_ability_token_can_trigger_webhook(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->eventFor($user);
        $endpoint = $this->endpointFor($user);
        $event->endpoints()->attach($endpoint);
        Sanctum::actingAs($user, ['create']);

        $response = $this->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('deliveries', ['event_id' => $event->id]);
    }

    public function test_read_only_token_cannot_retry_delivery(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->deliveryFor($user);
        $delivery->update(['status' => 'failed']);
        Sanctum::actingAs($user, ['read']);

        $response = $this->postJson("/api/v1/deliveries/{$delivery->id}/retry");

        $response->assertStatus(403);
        $this->assertSame('failed', $delivery->fresh()->status);
    }

    // -- Session-authenticated (web UI) requests are unaffected -----------------

    public function test_session_authenticated_request_is_not_restricted_by_token_abilities(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'From the dashboard',
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201);
    }
}
