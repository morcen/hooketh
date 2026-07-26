<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventSchemaValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rejects_schema_containing_regex_rule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/events', [
            'name' => 'order.created',
            'schema' => ['field' => 'required|regex:/^(a+)+$/'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('schema');
        $this->assertDatabaseCount('events', 0);
    }

    public function test_api_accepts_schema_without_regex_rule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/events', [
            'name' => 'order.created',
            'schema' => ['field' => 'required|string|max:255'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('events', 1);
    }

    public function test_api_rejects_updating_event_with_regex_schema(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create(['name' => 'order.created']);

        $response = $this->actingAs($user)->putJson("/api/v1/events/{$event->id}", [
            'schema' => ['field' => 'not_regex:/^(a+)+$/'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('schema');
    }

    public function test_dashboard_rejects_schema_containing_regex_rule(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->post(route('events.store'), [
            'name' => 'order.created',
            'schema' => ['field' => 'required|regex:/^(a+)+$/'],
        ]);

        $response->assertSessionHasErrors('schema');
        $this->assertDatabaseCount('events', 0);
    }
}
