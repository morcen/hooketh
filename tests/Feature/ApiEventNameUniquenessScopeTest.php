<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiEventNameUniquenessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_users_can_create_events_with_the_same_name_via_api(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        $this->actingAs($userA)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(201);

        $this->actingAs($userB)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(201);

        $this->assertDatabaseHas('events', ['user_id' => $userA->id, 'name' => 'order.created']);
        $this->assertDatabaseHas('events', ['user_id' => $userB->id, 'name' => 'order.created']);
    }

    public function test_same_user_cannot_create_duplicate_event_name_via_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(201);

        $this->actingAs($user)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('events', 1);
    }

    public function test_user_can_rename_event_to_a_name_used_by_another_user_via_api(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        Event::factory()->for($userA)->create(['name' => 'order.created']);
        $eventB = Event::factory()->for($userB)->create(['name' => 'order.updated']);

        $this->actingAs($userB)
            ->putJson("/api/v1/events/{$eventB->id}", ['name' => 'order.created'])
            ->assertStatus(200);

        $this->assertDatabaseHas('events', ['id' => $eventB->id, 'name' => 'order.created']);
    }

    public function test_user_cannot_rename_event_to_a_name_they_already_use_via_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Event::factory()->for($user)->create(['name' => 'order.created']);
        $second = Event::factory()->for($user)->create(['name' => 'order.updated']);

        $this->actingAs($user)
            ->putJson("/api/v1/events/{$second->id}", ['name' => 'order.created'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
