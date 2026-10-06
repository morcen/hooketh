<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for #47: Api\EventController had plenty of coverage
 * for its authorization edge cases (cross-tenant 404s, SSRF-adjacent
 * validation, soft-delete name reuse) but no test of its ordinary CRUD
 * success paths, unlike its web-dashboard counterpart (see
 * DashboardEventCreateTest/DashboardEventUpdateTest).
 */
class ApiEventControllerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_only_the_authenticated_users_events(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $event = Event::factory()->for($user)->create();
        Event::factory()->for($otherUser)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/events');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $event->id);
    }

    public function test_store_creates_an_event_and_attaches_the_given_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson('/api/v1/events', [
            'name' => 'order.created',
            'description' => 'Fired when an order is created',
            'endpoint_ids' => [$endpoint->id],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'order.created');
        $response->assertJsonCount(1, 'endpoints');

        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'name' => 'order.created',
        ]);

        $event = Event::where('user_id', $user->id)->where('name', 'order.created')->firstOrFail();
        $this->assertTrue($event->endpoints->contains($endpoint));
    }

    public function test_store_does_not_attach_an_endpoint_owned_by_another_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $otherUsersEndpoint = Endpoint::factory()->for(User::factory()->withPersonalTeam()->create())->create();

        $response = $this->actingAs($user)->postJson('/api/v1/events', [
            'name' => 'order.created',
            'endpoint_ids' => [$otherUsersEndpoint->id],
        ]);

        $response->assertStatus(201);

        $event = Event::where('user_id', $user->id)->where('name', 'order.created')->firstOrFail();
        $this->assertCount(0, $event->endpoints);
    }

    public function test_show_returns_the_event_with_its_endpoints_and_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->getJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('id', $event->id);
        $response->assertJsonCount(1, 'endpoints');
    }

    public function test_update_persists_changes_and_syncs_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $oldEndpoint = Endpoint::factory()->for($user)->create();
        $newEndpoint = Endpoint::factory()->for($user)->create();
        $event->endpoints()->attach($oldEndpoint);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'name' => 'order.updated',
            'endpoint_ids' => [$newEndpoint->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'order.updated');

        $event->refresh();
        $this->assertSame('order.updated', $event->name);
        $this->assertTrue($event->endpoints->contains($newEndpoint));
        $this->assertFalse($event->endpoints->contains($oldEndpoint));
    }

    public function test_destroy_soft_deletes_the_event(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($event);
    }
}
