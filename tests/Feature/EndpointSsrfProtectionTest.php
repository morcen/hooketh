<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointSsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_endpoint_with_loopback_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Malicious Endpoint',
            'url' => 'http://127.0.0.1/steal-data',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
        $this->assertDatabaseCount('endpoints', 0);
    }

    public function test_creating_endpoint_with_cloud_metadata_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Metadata Endpoint',
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
    }

    public function test_creating_endpoint_with_private_network_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Internal Endpoint',
            'url' => 'http://10.0.0.5/internal-api',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
    }

    public function test_creating_endpoint_with_public_ip_url_is_allowed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Public Endpoint',
            'url' => 'http://8.8.8.8/webhook',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('endpoints', ['url' => 'http://8.8.8.8/webhook']);
    }

    public function test_updating_endpoint_to_loopback_url_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $response = $this->actingAs($user)->putJson("/api/v1/endpoints/{$endpoint->id}", [
            'url' => 'http://127.0.0.1/steal-data',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'url' => $endpoint->url]);
    }
}
