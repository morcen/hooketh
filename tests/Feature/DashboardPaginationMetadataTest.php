<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPaginationMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliveries_index_exposes_from_to_and_total_for_pagination_summary(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();
        Delivery::factory()->for($event)->for($endpoint)->count(25)->create();

        $response = $this->actingAs($user)->get(route('deliveries'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->where('deliveries.from', 1)
                ->where('deliveries.to', 20)
                ->where('deliveries.total', 25)
        );
    }

    public function test_events_index_exposes_from_to_and_total_for_pagination_summary(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Event::factory()->for($user)->count(16)->create();

        $response = $this->actingAs($user)->get(route('events'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->where('events.from', 1)
                ->where('events.to', 15)
                ->where('events.total', 16)
        );
    }

    public function test_endpoints_index_exposes_from_to_and_total_for_pagination_summary(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Endpoint::factory()->for($user)->count(16)->create();

        $response = $this->actingAs($user)->get(route('endpoints'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Endpoints/Index')
                ->where('endpoints.from', 1)
                ->where('endpoints.to', 15)
                ->where('endpoints.total', 16)
        );
    }
}
