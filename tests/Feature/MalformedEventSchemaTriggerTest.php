<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers events whose schema became unusable after creation (e.g. it was
 * inserted directly, or predates ValidEventSchema's dry-run check). Both
 * trigger endpoints must fail gracefully rather than crash with an
 * uncaught exception on every subsequent trigger call.
 */
class MalformedEventSchemaTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_trigger_fails_gracefully_for_a_malformed_schema(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create([
            'name' => 'order.created',
            'schema' => ['field' => 'required|thisruledoesnotexist'],
        ]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        $response = $this->actingAs($user)->postJson('/api/v1/webhooks/trigger/'.$event->name, [
            'payload' => ['field' => 'value'],
        ]);

        $response->assertStatus(500);
        $response->assertJson(['message' => 'This event has an invalid schema configuration and cannot currently accept triggers.']);
        $this->assertDatabaseCount('deliveries', 0);

        Http::assertNothingSent();
    }

    public function test_dashboard_trigger_fails_gracefully_for_a_malformed_schema(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create([
            'name' => 'order.created',
            'schema' => ['field' => 'required|thisruledoesnotexist'],
        ]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);
        $event->endpoints()->attach($endpoint);

        // The dashboard's trigger action validates the schema against the
        // raw request data rather than the payload sub-array (a separate,
        // already-tracked bug), so "field" needs to be present at the top
        // level here for the bogus rule to actually be resolved/invoked.
        $response = $this->actingAs($user)->post(route('events.trigger', $event), [
            'payload' => ['field' => 'value'],
            'field' => 'value',
        ]);

        $response->assertRedirect(route('events'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('deliveries', 0);

        Http::assertNothingSent();
    }
}
