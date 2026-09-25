<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointRegenerateSecretTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_regenerate_secret_returns_the_new_plain_secret_and_persists_it(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'secret_key' => 'original-secret-value',
        ]);

        $response = $this->actingAs($user)
            ->post("/endpoints/{$endpoint->id}/regenerate-secret");

        $response->assertOk();
        $response->assertJsonStructure(['plain_secret']);

        $plainSecret = $response->json('plain_secret');
        $this->assertNotSame('original-secret-value', $plainSecret);
        $this->assertSame($plainSecret, $endpoint->fresh()->secret_key);
    }

    public function test_web_regenerate_secret_failure_response_carries_no_plain_secret(): void
    {
        config(['webhooks.secret_rotation_rate_limit' => 1]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'secret_key' => 'original-secret-value',
        ]);

        $this->actingAs($user)
            ->post("/endpoints/{$endpoint->id}/regenerate-secret")
            ->assertOk();

        $this->actingAs($user)
            ->post("/endpoints/{$endpoint->id}/regenerate-secret")
            ->assertStatus(429);
    }
}
