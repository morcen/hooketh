<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretRotationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_regenerate_secret_action_is_rate_limited(): void
    {
        config(['webhooks.secret_rotation_rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post("/endpoints/{$endpoint->id}/regenerate-secret")
                ->assertOk();
        }

        $this->actingAs($user)
            ->post("/endpoints/{$endpoint->id}/regenerate-secret")
            ->assertStatus(429);
    }

    public function test_v1_api_regenerate_secret_action_is_rate_limited(): void
    {
        config(['webhooks.secret_rotation_rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->postJson("/api/v1/endpoints/{$endpoint->id}/regenerate-secret")
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson("/api/v1/endpoints/{$endpoint->id}/regenerate-secret")
            ->assertStatus(429);
    }

    public function test_deprecated_api_regenerate_secret_action_is_rate_limited(): void
    {
        config(['webhooks.secret_rotation_rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->postJson("/api/endpoints/{$endpoint->id}/regenerate-secret")
                ->assertOk();
        }

        $this->actingAs($user)
            ->postJson("/api/endpoints/{$endpoint->id}/regenerate-secret")
            ->assertStatus(429);
    }

    public function test_regenerate_secret_rate_limit_is_scoped_per_user(): void
    {
        config(['webhooks.secret_rotation_rate_limit' => 1]);

        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $endpointA = Endpoint::factory()->for($userA)->create();
        $endpointB = Endpoint::factory()->for($userB)->create();

        $this->actingAs($userA)->post("/endpoints/{$endpointA->id}/regenerate-secret")->assertOk();
        $this->actingAs($userA)->post("/endpoints/{$endpointA->id}/regenerate-secret")->assertStatus(429);

        $this->actingAs($userB)->post("/endpoints/{$endpointB->id}/regenerate-secret")->assertOk();
    }
}
