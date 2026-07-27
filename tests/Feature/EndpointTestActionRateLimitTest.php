<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EndpointTestActionRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_webhook_action_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config(['webhooks.rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook']);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post("/endpoints/{$endpoint->id}/test")
                ->assertOk();
        }

        $response = $this->actingAs($user)->post("/endpoints/{$endpoint->id}/test");

        $response->assertStatus(429);
    }

    public function test_test_webhook_action_rate_limit_is_scoped_per_user(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config(['webhooks.rate_limit' => 1]);

        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();
        $endpointA = Endpoint::factory()->for($userA)->create(['url' => 'http://8.8.8.8/webhook']);
        $endpointB = Endpoint::factory()->for($userB)->create(['url' => 'http://8.8.8.8/webhook']);

        $this->actingAs($userA)->post("/endpoints/{$endpointA->id}/test")->assertOk();
        $this->actingAs($userA)->post("/endpoints/{$endpointA->id}/test")->assertStatus(429);

        $this->actingAs($userB)->post("/endpoints/{$endpointB->id}/test")->assertOk();
    }
}
