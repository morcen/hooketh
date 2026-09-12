<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_update_persists_new_payload_and_schema(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create([
            'name' => 'order.created',
            'payload' => ['old' => 'value'],
            'schema' => ['old' => 'required|string'],
        ]);

        $response = $this->actingAs($user)->put(route('events.update', $event), [
            'name' => 'order.created',
            'event_type' => $event->event_type,
            'description' => $event->description,
            'payload' => ['new' => 'value', 'nested' => ['count' => 2]],
            'schema' => ['new' => 'required|string'],
        ]);

        $response->assertRedirect(route('events'));
        $response->assertSessionHas('success');

        $event->refresh();

        $this->assertSame(['new' => 'value', 'nested' => ['count' => 2]], $event->payload);
        $this->assertSame(['new' => 'required|string'], $event->schema);
    }

    public function test_dashboard_update_rejects_request_from_another_users_event(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($owner)->create([
            'name' => 'order.created',
            'payload' => ['original' => true],
        ]);

        $response = $this->actingAs($otherUser)->put(route('events.update', $event), [
            'name' => 'order.created',
            'payload' => ['hijacked' => true],
        ]);

        $response->assertStatus(404);
        $this->assertSame(['original' => true], $event->fresh()->payload);
    }
}
