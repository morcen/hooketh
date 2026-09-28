<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventsEditEndpointSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_event_page_exposes_currently_subscribed_endpoints(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $subscribed = Endpoint::factory()->for($user)->create();
        Endpoint::factory()->for($user)->create();

        $event->endpoints()->sync([$subscribed->id]);

        $response = $this->actingAs($user)->get(route('events.edit', $event));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Events/Edit')
                ->where('event.endpoints.0.id', $subscribed->id)
                ->has('event.endpoints', 1)
        );
    }

    public function test_saving_endpoint_subscriptions_from_the_edit_page_syncs_them(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpointToSubscribe = Endpoint::factory()->for($user)->create();
        $endpointToKeepUnsubscribed = Endpoint::factory()->for($user)->create();

        $response = $this->actingAs($user)->post(route('events.endpoints', $event), [
            'endpoint_ids' => [$endpointToSubscribe->id],
        ]);

        $response->assertRedirect(route('events'));

        $this->assertSame(
            [$endpointToSubscribe->id],
            $event->endpoints()->pluck('endpoints.id')->all()
        );
        $this->assertNotContains($endpointToKeepUnsubscribed->id, $event->endpoints()->pluck('endpoints.id')->all());
    }
}
