<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for issue #102: ownership checks across the API and web
 * controllers used to return 403 Forbidden when a resource belonged to
 * another user, while a nonexistent ID produced a 404 from Laravel's
 * implicit route-model binding. That distinction let any authenticated
 * user enumerate the existence of other tenants' resources. Every check
 * should now return 404 in both cases.
 */
class CrossTenantResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function otherUsersEndpoint(): Endpoint
    {
        $owner = User::factory()->withPersonalTeam()->create();

        return Endpoint::factory()->create(['user_id' => $owner->id]);
    }

    private function otherUsersEvent(): Event
    {
        $owner = User::factory()->withPersonalTeam()->create();

        return Event::factory()->create(['user_id' => $owner->id]);
    }

    private function otherUsersDelivery(): Delivery
    {
        $event = $this->otherUsersEvent();
        $endpoint = Endpoint::factory()->create(['user_id' => $event->user_id]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    // -- API: endpoints --------------------------------------------------

    public function test_api_show_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->getJson("/api/v1/endpoints/{$endpoint->id}");

        $response->assertStatus(404);
    }

    public function test_api_update_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->putJson("/api/v1/endpoints/{$endpoint->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(404);
    }

    public function test_api_destroy_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->deleteJson("/api/v1/endpoints/{$endpoint->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'deleted_at' => null]);
    }

    public function test_api_regenerate_secret_for_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->postJson("/api/v1/endpoints/{$endpoint->id}/regenerate-secret");

        $response->assertStatus(404);
    }

    // -- API: events -------------------------------------------------------

    public function test_api_show_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(404);
    }

    public function test_api_update_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'name' => 'hijacked.event',
        ]);

        $response->assertStatus(404);
    }

    public function test_api_destroy_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    // -- API: deliveries -----------------------------------------------------

    public function test_api_show_delivery_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->otherUsersDelivery();

        $response = $this->actingAs($user)->getJson("/api/v1/deliveries/{$delivery->id}");

        $response->assertStatus(404);
    }

    public function test_api_retry_delivery_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->otherUsersDelivery();

        $response = $this->actingAs($user)->postJson("/api/v1/deliveries/{$delivery->id}/retry");

        $response->assertStatus(404);
    }

    // -- Web: endpoints ------------------------------------------------------

    public function test_web_update_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->put("/endpoints/{$endpoint->id}", [
            'name' => 'Hijacked',
        ]);

        $response->assertStatus(404);
    }

    public function test_web_destroy_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->delete("/endpoints/{$endpoint->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'deleted_at' => null]);
    }

    public function test_web_regenerate_secret_for_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->post("/endpoints/{$endpoint->id}/regenerate-secret");

        $response->assertStatus(404);
    }

    public function test_web_test_action_for_endpoint_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = $this->otherUsersEndpoint();

        $response = $this->actingAs($user)->post("/endpoints/{$endpoint->id}/test");

        $response->assertStatus(404);
    }

    // -- Web: events -----------------------------------------------------------

    public function test_web_update_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->put("/events/{$event->id}", [
            'name' => 'hijacked.event',
        ]);

        $response->assertStatus(404);
    }

    public function test_web_destroy_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->delete("/events/{$event->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'deleted_at' => null]);
    }

    public function test_web_sync_endpoints_for_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->post("/events/{$event->id}/endpoints", [
            'endpoint_ids' => [],
        ]);

        $response->assertStatus(404);
    }

    public function test_web_trigger_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->post("/events/{$event->id}/trigger", [
            'payload' => ['foo' => 'bar'],
        ]);

        $response->assertStatus(404);
    }

    public function test_web_edit_event_owned_by_another_user_returns_404(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = $this->otherUsersEvent();

        $response = $this->actingAs($user)->get("/events/{$event->id}/edit");

        $response->assertStatus(404);
    }
}
