<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventsSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_matching_event_beyond_the_first_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        // Fill more than a full page (15) with events that don't match the search term.
        for ($i = 0; $i < 15; $i++) {
            Event::factory()->for($user)->create(['name' => "noise.event.{$i}"]);
        }
        $target = Event::factory()->for($user)->create(['name' => 'order.created.unique']);

        $response = $this->actingAs($user)->get(route('events', ['search' => 'order.created.unique']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.id', $target->id)
                ->where('filters.search', 'order.created.unique')
        );
    }

    public function test_type_filter_finds_a_matching_event_beyond_the_first_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        for ($i = 0; $i < 15; $i++) {
            Event::factory()->for($user)->create(['event_type' => 'noise.type']);
        }
        $target = Event::factory()->for($user)->create(['event_type' => 'order.confirmed']);

        $response = $this->actingAs($user)->get(route('events', ['type' => 'order.confirmed']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.id', $target->id)
                ->where('filters.type', 'order.confirmed')
        );
    }

    public function test_event_types_prop_lists_types_across_all_pages_not_just_the_current_one(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        for ($i = 0; $i < 15; $i++) {
            Event::factory()->for($user)->create(['event_type' => 'noise.type']);
        }
        Event::factory()->for($user)->create(['event_type' => 'order.confirmed']);

        $response = $this->actingAs($user)->get(route('events'));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->where('eventTypes', ['noise.type', 'order.confirmed'])
        );
    }

    public function test_search_cannot_be_used_to_view_another_users_events(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $otherUser = User::factory()->withPersonalTeam()->create();
        Event::factory()->for($otherUser)->create(['name' => 'order.created']);

        $response = $this->actingAs($user)->get(route('events', ['search' => 'order.created']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Index')
                ->has('events.data', 0)
        );
    }
}
