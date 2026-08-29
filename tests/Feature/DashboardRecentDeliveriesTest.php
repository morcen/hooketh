<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRecentDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_when_a_recent_deliverys_endpoint_is_soft_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $delivery = Delivery::factory()->for($event)->for($endpoint)->create();

        $endpoint->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->has('recentDeliveries', 1)
                ->where('recentDeliveries.0.id', $delivery->id)
                ->where('recentDeliveries.0.endpoint', null)
        );
    }

    public function test_dashboard_loads_when_a_recent_deliverys_event_is_soft_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        Delivery::factory()->for($event)->for($endpoint)->create();

        $event->delete();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->has('recentDeliveries', 0)
        );
    }
}
