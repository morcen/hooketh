<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointUrlColumnLengthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a valid https URL of the given total length using a public IP
     * host (so SafeWebhookUrl's SSRF check doesn't reject it), padded with
     * a long query string.
     */
    private function urlOfLength(int $length): string
    {
        $prefix = 'https://8.8.8.8/webhook?padding=';
        $padding = str_repeat('a', max(0, $length - strlen($prefix)));

        return $prefix.$padding;
    }

    public function test_creating_endpoint_with_url_longer_than_255_characters_is_allowed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $url = $this->urlOfLength(1000);

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Long URL Endpoint',
            'url' => $url,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('endpoints', ['url' => $url]);
    }

    public function test_creating_endpoint_with_url_at_the_2048_character_validation_limit_is_allowed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $url = $this->urlOfLength(2048);
        $this->assertSame(2048, strlen($url));

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Max Length URL Endpoint',
            'url' => $url,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('endpoints', ['url' => $url]);
    }

    public function test_updating_endpoint_with_url_longer_than_255_characters_is_allowed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $url = $this->urlOfLength(1500);

        $response = $this->actingAs($user)->putJson("/api/v1/endpoints/{$endpoint->id}", [
            'url' => $url,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id, 'url' => $url]);
    }

    public function test_creating_endpoint_with_url_longer_than_2048_characters_is_rejected(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $url = $this->urlOfLength(2049);

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'Too Long URL Endpoint',
            'url' => $url,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('url');
        $this->assertDatabaseCount('endpoints', 0);
    }
}
