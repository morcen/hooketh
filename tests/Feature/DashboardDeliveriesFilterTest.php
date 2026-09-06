<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDeliveriesFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_name_filter_only_returns_matching_events_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $targetEvent = Event::factory()->for($user)->create(['name' => 'user.created']);
        $otherEvent = Event::factory()->for($user)->create(['name' => 'order.updated']);

        $targetDelivery = Delivery::factory()->for($targetEvent)->for($endpoint)->create();
        Delivery::factory()->for($otherEvent)->for($endpoint)->create();

        $response = $this->actingAs($user)->get(route('deliveries', ['event_name' => 'user.cre']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.id', $targetDelivery->id)
                ->where('filters.event_name', 'user.cre')
        );
    }

    public function test_date_range_filters_only_return_deliveries_within_range(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();

        $withinRange = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-06-15 12:00:00',
        ]);
        Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-05-01 12:00:00',
        ]);
        Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-07-01 12:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('deliveries', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.id', $withinRange->id)
                ->where('filters.from_date', '2026-06-01')
                ->where('filters.to_date', '2026-06-30')
        );
    }

    public function test_event_name_filter_cannot_be_used_to_view_another_users_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        Event::factory()->for($user)->create(['name' => 'user.created']);

        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherEndpoint = Endpoint::factory()->for($otherUser)->create();
        $otherEvent = Event::factory()->for($otherUser)->create(['name' => 'user.created']);
        Delivery::factory()->for($otherEvent)->for($otherEndpoint)->create();

        $response = $this->actingAs($user)->get(route('deliveries', ['event_name' => 'user.created']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 0)
        );
    }
}
