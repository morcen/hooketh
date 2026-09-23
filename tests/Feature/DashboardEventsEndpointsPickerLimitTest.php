<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventsEndpointsPickerLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_index_caps_the_endpoint_picker_list(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        Endpoint::factory()->for($user)->count(501)->create();

        $response = $this->actingAs($user)->get(route('events'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->has('endpoints', 500)
        );
    }

    public function test_edit_event_caps_the_endpoint_picker_list(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();

        Endpoint::factory()->for($user)->count(501)->create();

        $response = $this->actingAs($user)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Edit')
                ->has('endpoints', 500)
        );
    }
}
