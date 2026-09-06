<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDeliveriesEventFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_id_filter_only_returns_that_events_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $targetEvent = Event::factory()->for($user)->create(['name' => 'user.created']);
        $otherEvent = Event::factory()->for($user)->create(['name' => 'user.deleted']);

        $targetDelivery = Delivery::factory()->for($targetEvent)->for($endpoint)->create();
        Delivery::factory()->for($otherEvent)->for($endpoint)->create();

        $response = $this->actingAs($user)->get(route('deliveries', ['event_id' => $targetEvent->id]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 1)
                ->where('deliveries.data.0.id', $targetDelivery->id)
                ->where('filters.event_id', (string) $targetEvent->id)
                ->where('filteredEvent.id', $targetEvent->id)
                ->where('filteredEvent.name', $targetEvent->name)
        );
    }

    public function test_without_event_id_filter_all_of_the_users_deliveries_are_returned(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $eventOne = Event::factory()->for($user)->create();
        $eventTwo = Event::factory()->for($user)->create();

        Delivery::factory()->for($eventOne)->for($endpoint)->create();
        Delivery::factory()->for($eventTwo)->for($endpoint)->create();

        $response = $this->actingAs($user)->get(route('deliveries'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 2)
                ->where('filteredEvent', null)
        );
    }

    public function test_event_id_filter_cannot_be_used_to_view_another_users_event_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $ownEvent = Event::factory()->for($user)->create();
        Delivery::factory()->for($ownEvent)->for($endpoint)->create();

        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherEvent = Event::factory()->for($otherUser)->create();
        $otherEndpoint = Endpoint::factory()->for($otherUser)->create();
        Delivery::factory()->for($otherEvent)->for($otherEndpoint)->create();

        $response = $this->actingAs($user)->get(route('deliveries', ['event_id' => $otherEvent->id]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 0)
                ->where('filteredEvent', null)
        );
    }
}
