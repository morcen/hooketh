<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Direct coverage for issue #91: the shared `webhook-trigger` rate limiter
 * (RateLimiter::for('webhook-trigger', ...) in AppServiceProvider) already
 * has indirect coverage via the endpoint-test, delivery-retry, and
 * dashboard-trigger call sites, but the documented trigger endpoint itself
 * -- POST /api/v1/webhooks/trigger/{eventName} -- had no direct 429 test.
 */
class WebhookTriggerRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_trigger_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config(['webhooks.rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        Sanctum::actingAs($user, ['create']);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/webhooks/trigger/'.$event->name, ['payload' => ['foo' => 'bar']])
                ->assertOk();
        }

        $response = $this->postJson('/api/v1/webhooks/trigger/'.$event->name, ['payload' => ['foo' => 'bar']]);

        $response->assertStatus(429);
    }

    public function test_api_trigger_rate_limit_is_scoped_per_user(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        config(['webhooks.rate_limit' => 1]);

        $userA = User::factory()->withPersonalTeam()->create();
        $eventA = Event::factory()->for($userA)->create(['name' => 'order.created']);
        $endpointA = Endpoint::factory()->for($userA)->create(['is_active' => true]);
        $eventA->endpoints()->attach($endpointA);

        $userB = User::factory()->withPersonalTeam()->create();
        $eventB = Event::factory()->for($userB)->create(['name' => 'order.created']);
        $endpointB = Endpoint::factory()->for($userB)->create(['is_active' => true]);
        $eventB->endpoints()->attach($endpointB);

        Sanctum::actingAs($userA, ['create']);

        $this->postJson('/api/v1/webhooks/trigger/'.$eventA->name, ['payload' => ['foo' => 'bar']])
            ->assertOk();

        $this->postJson('/api/v1/webhooks/trigger/'.$eventA->name, ['payload' => ['foo' => 'bar']])
            ->assertStatus(429);

        Sanctum::actingAs($userB, ['create']);

        $this->postJson('/api/v1/webhooks/trigger/'.$eventB->name, ['payload' => ['foo' => 'bar']])
            ->assertOk();
    }
}
