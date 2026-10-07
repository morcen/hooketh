<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for #47: no test previously asserted that `secret_key`
 * never leaks into a JSON response. Endpoint::$hidden hides it on every
 * Eloquent serialization path, but create() and regenerate-secret build
 * their response manually (array_merge($endpoint->toArray(), ['plain_secret'
 * => ...])) specifically to deliberately reveal the plaintext value once,
 * right after it's generated. These tests pin down both halves of that
 * contract: the encrypted secret_key column is never exposed, and the
 * intentional plain_secret reveal still works.
 */
class EndpointSecretKeyHiddenTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_response_exposes_plain_secret_but_not_secret_key(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/endpoints', [
            'name' => 'My Endpoint',
            'url' => 'https://example.com/webhook',
        ]);

        $response->assertStatus(201);
        $response->assertJsonMissingPath('secret_key');
        $this->assertIsString($response->json('plain_secret'));
        $this->assertNotEmpty($response->json('plain_secret'));

        $endpoint = Endpoint::where('user_id', $user->id)->firstOrFail();
        $this->assertSame($endpoint->secret_key, $response->json('plain_secret'));
    }

    public function test_regenerate_secret_response_exposes_plain_secret_but_not_secret_key(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson("/api/v1/endpoints/{$endpoint->id}/regenerate-secret");

        $response->assertStatus(200);
        $response->assertJsonMissingPath('secret_key');
        $this->assertIsString($response->json('plain_secret'));

        $endpoint->refresh();
        $this->assertSame($endpoint->secret_key, $response->json('plain_secret'));
    }

    public function test_index_show_and_update_responses_never_expose_secret_key(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $this->actingAs($user)->getJson('/api/v1/endpoints')
            ->assertStatus(200)
            ->assertJsonMissingPath('data.0.secret_key');

        $this->actingAs($user)->getJson("/api/v1/endpoints/{$endpoint->id}")
            ->assertStatus(200)
            ->assertJsonMissingPath('secret_key');

        $this->actingAs($user)->putJson("/api/v1/endpoints/{$endpoint->id}", [
            'name' => 'Renamed',
        ])
            ->assertStatus(200)
            ->assertJsonMissingPath('secret_key');
    }
}
