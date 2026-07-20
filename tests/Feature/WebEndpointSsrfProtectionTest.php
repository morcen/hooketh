<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebEndpointSsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_endpoint_via_dashboard_with_loopback_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/endpoints', [
            'name' => 'Malicious Endpoint',
            'url' => 'http://127.0.0.1/steal-data',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseCount('endpoints', 0);
    }

    public function test_creating_endpoint_via_dashboard_with_cloud_metadata_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post('/endpoints', [
            'name' => 'Metadata Endpoint',
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseCount('endpoints', 0);
    }

    public function test_updating_endpoint_via_dashboard_to_private_network_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $response = $this->actingAs($user)->put("/endpoints/{$endpoint->id}", [
            'url' => 'http://10.0.0.5/internal-api',
        ]);

        $response->assertSessionHasErrors('url');
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'url' => $endpoint->url]);
    }

    public function test_test_webhook_action_is_blocked_for_endpoint_pointing_at_metadata_service(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();

        // Bypasses controller validation to simulate a DNS-rebinding scenario
        // where the endpoint URL resolved safely at creation time but not now.
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $response = $this->actingAs($user)->post("/endpoints/{$endpoint->id}/test");

        Http::assertNothingSent();
        $response->assertInertia(
            fn ($page) => $page
            ->where('testResult.success', false)
            ->where('testResult.response_code', null)
        );
    }

    public function test_test_webhook_action_makes_a_request_for_a_safe_url(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook']);

        $response = $this->actingAs($user)->post("/endpoints/{$endpoint->id}/test");

        Http::assertSent(fn ($request) => $request->url() === 'http://8.8.8.8/webhook');
        $response->assertInertia(
            fn ($page) => $page
            ->where('testResult.success', true)
            ->where('testResult.response_code', 200)
        );
    }
}
