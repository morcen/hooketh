<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_create_persists_payload_and_schema(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'name' => 'order.created',
            'event_type' => 'order.created',
            'description' => 'Fired when an order is created',
            'payload' => ['user_id' => 1, 'nested' => ['count' => 2]],
            'schema' => ['user_id' => 'required|integer'],
        ]);

        $response->assertRedirect(route('events'));
        $response->assertSessionHas('success');

        $event = Event::where('user_id', $user->id)->where('name', 'order.created')->firstOrFail();

        $this->assertSame(['user_id' => 1, 'nested' => ['count' => 2]], $event->payload);
        $this->assertSame(['user_id' => 'required|integer'], $event->schema);
    }

    public function test_dashboard_create_rejects_duplicate_name_for_same_user(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Event::factory()->for($user)->create(['name' => 'order.created']);

        $response = $this->actingAs($user)->post(route('events.store'), [
            'name' => 'order.created',
            'payload' => ['user_id' => 1],
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(1, Event::where('user_id', $user->id)->where('name', 'order.created')->count());
    }
}
