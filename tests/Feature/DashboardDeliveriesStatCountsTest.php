<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardDeliveriesStatCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stat_card_counts_reflect_all_matching_records_not_just_current_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();

        Delivery::factory()->for($event)->for($endpoint)->count(15)->create(['status' => 'success']);
        Delivery::factory()->for($event)->for($endpoint)->count(5)->create(['status' => 'failed']);
        Delivery::factory()->for($event)->for($endpoint)->count(3)->create(['status' => 'pending']);
        Delivery::factory()->for($event)->for($endpoint)->count(2)->create(['status' => 'retrying']);

        $response = $this->actingAs($user)->get(route('deliveries'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 20)
                ->where('deliveries.total', 25)
                ->where('deliveryCounts.successful', 15)
                ->where('deliveryCounts.failed', 5)
                ->where('deliveryCounts.pending', 5)
        );
    }

    public function test_stat_card_counts_respect_active_filters(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();

        Delivery::factory()->for($event)->for($endpoint)->count(3)->create(['status' => 'success']);
        Delivery::factory()->for($event)->for($endpoint)->count(2)->create(['status' => 'failed']);

        $response = $this->actingAs($user)->get(route('deliveries', ['status' => 'failed']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->where('deliveryCounts.successful', 0)
                ->where('deliveryCounts.failed', 2)
                ->where('deliveryCounts.pending', 0)
        );
    }

    public function test_stat_card_counts_do_not_include_another_users_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();
        Delivery::factory()->for($event)->for($endpoint)->create(['status' => 'success']);

        $otherUser = User::factory()->withPersonalTeam()->create();
        $otherEndpoint = Endpoint::factory()->for($otherUser)->create();
        $otherEvent = Event::factory()->for($otherUser)->create();
        Delivery::factory()->for($otherEvent)->for($otherEndpoint)->count(10)->create(['status' => 'success']);

        $response = $this->actingAs($user)->get(route('deliveries'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->where('deliveryCounts.successful', 1)
        );
    }
}
