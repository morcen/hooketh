<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletedEventNameReuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_reuse_the_name_of_a_soft_deleted_event_via_web(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $original = Event::factory()->for($user)->create(['name' => 'order.created']);
        $original->delete();

        $this->assertSoftDeleted($original);

        $this->actingAs($user)
            ->post('/events', ['name' => 'order.created'])
            ->assertRedirect(route('events'));

        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'name' => 'order.created',
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_rename_an_event_to_the_name_of_a_soft_deleted_event_via_web(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $deleted = Event::factory()->for($user)->create(['name' => 'order.created']);
        $deleted->delete();
        $active = Event::factory()->for($user)->create(['name' => 'order.updated']);

        $this->actingAs($user)
            ->put("/events/{$active->id}", ['name' => 'order.created'])
            ->assertRedirect(route('events'));

        $this->assertDatabaseHas('events', [
            'id' => $active->id,
            'name' => 'order.created',
        ]);
    }

    public function test_user_still_cannot_reuse_the_name_of_an_active_event_via_web(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Event::factory()->for($user)->create(['name' => 'order.created']);

        $this->actingAs($user)
            ->post('/events', ['name' => 'order.created'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('events', 1);
    }

    public function test_user_can_reuse_the_name_of_a_soft_deleted_event_via_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $original = Event::factory()->for($user)->create(['name' => 'order.created']);
        $original->delete();

        $this->actingAs($user)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(201);

        $this->assertDatabaseHas('events', [
            'user_id' => $user->id,
            'name' => 'order.created',
            'deleted_at' => null,
        ]);
    }

    public function test_user_still_cannot_reuse_the_name_of_an_active_event_via_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Event::factory()->for($user)->create(['name' => 'order.created']);

        $this->actingAs($user)
            ->postJson('/api/v1/events', ['name' => 'order.created'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('events', 1);
    }
}
