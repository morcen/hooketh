<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEndpointsSearchFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_a_matching_endpoint_beyond_the_first_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        // Fill more than a full page (15) with endpoints that don't match the search term.
        for ($i = 0; $i < 15; $i++) {
            Endpoint::factory()->for($user)->create(['name' => "Noise Endpoint {$i}"]);
        }
        $target = Endpoint::factory()->for($user)->create(['name' => 'Unique Billing Receiver']);

        $response = $this->actingAs($user)->get(route('endpoints', ['search' => 'Unique Billing Receiver']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Endpoints/Index')
                ->has('endpoints.data', 1)
                ->where('endpoints.data.0.id', $target->id)
                ->where('filters.search', 'Unique Billing Receiver')
        );
    }

    public function test_status_filter_finds_a_matching_endpoint_beyond_the_first_page(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        for ($i = 0; $i < 15; $i++) {
            Endpoint::factory()->for($user)->create(['is_active' => true]);
        }
        $target = Endpoint::factory()->for($user)->create(['is_active' => false]);

        $response = $this->actingAs($user)->get(route('endpoints', ['status' => 'inactive']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Endpoints/Index')
                ->has('endpoints.data', 1)
                ->where('endpoints.data.0.id', $target->id)
                ->where('filters.status', 'inactive')
        );
    }

    public function test_search_cannot_be_used_to_view_another_users_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $otherUser = User::factory()->withPersonalTeam()->create();
        Endpoint::factory()->for($otherUser)->create(['name' => 'Other Users Endpoint']);

        $response = $this->actingAs($user)->get(route('endpoints', ['search' => 'Other Users Endpoint']));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Endpoints/Index')
                ->has('endpoints.data', 0)
        );
    }
}
