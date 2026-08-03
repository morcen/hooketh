<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardEventTriggerRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_trigger_action_is_rate_limited(): void
    {
        Http::fake();
        config(['webhooks.rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($user)
                ->post(route('events.trigger', $event), ['payload' => ['foo' => 'bar']])
                ->assertSessionHasNoErrors();
        }

        $response = $this->actingAs($user)
            ->post(route('events.trigger', $event), ['payload' => ['foo' => 'bar']]);

        $response->assertStatus(429);
    }

    public function test_dashboard_trigger_action_rate_limit_is_scoped_per_user(): void
    {
        Http::fake();
        config(['webhooks.rate_limit' => 1]);

        $userA = User::factory()->withPersonalTeam()->create();
        $eventA = Event::factory()->for($userA)->create(['name' => 'order.created']);
        $endpointA = Endpoint::factory()->for($userA)->create(['is_active' => true]);
        $eventA->endpoints()->attach($endpointA);

        $userB = User::factory()->withPersonalTeam()->create();
        $eventB = Event::factory()->for($userB)->create(['name' => 'order.created']);
        $endpointB = Endpoint::factory()->for($userB)->create(['is_active' => true]);
        $eventB->endpoints()->attach($endpointB);

        $this->actingAs($userA)
            ->post(route('events.trigger', $eventA), ['payload' => ['foo' => 'bar']])
            ->assertSessionHasNoErrors();
        $this->actingAs($userA)
            ->post(route('events.trigger', $eventA), ['payload' => ['foo' => 'bar']])
            ->assertStatus(429);

        $this->actingAs($userB)
            ->post(route('events.trigger', $eventB), ['payload' => ['foo' => 'bar']])
            ->assertSessionHasNoErrors();
    }
}
