<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The dashboard's "Trigger Event" action must validate the extracted
 * payload against the event's schema, not the raw request (which only has
 * a top-level "payload" key) — matching the API trigger endpoint's behavior.
 */
class EventTriggerSchemaValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_trigger_rejects_payload_that_fails_the_event_schema(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create([
            'name' => 'order.created',
            'schema' => ['order_id' => 'required|integer'],
        ]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['order_id' => 'not-an-integer'],
        ]);

        $response->assertSessionHasErrors('order_id');
        $this->assertDatabaseCount('deliveries', 0);

        Http::assertNothingSent();
    }

    public function test_dashboard_trigger_accepts_payload_that_matches_the_event_schema(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create([
            'name' => 'order.created',
            'schema' => ['order_id' => 'required|integer'],
        ]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['order_id' => 42],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseCount('deliveries', 1);
    }
}
