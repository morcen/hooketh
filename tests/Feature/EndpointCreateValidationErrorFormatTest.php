<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndpointCreateValidationErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The "Create Endpoint" modal submits via a raw axios call (rather than
     * Inertia's form helper) so it can read back the one-time plain_secret
     * on success, which means the frontend receives Laravel's raw JSON
     * validation-error shape and must flatten it itself. This test pins
     * that shape down: each field's errors are an array of messages, not a
     * single string.
     */
    public function test_validation_errors_for_ajax_endpoint_creation_are_returned_as_arrays_per_field(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->postJson('/endpoints', [
            'name' => '',
            'url' => 'not-a-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'url']);
        $this->assertIsArray($response->json('errors.name'));
        $this->assertIsArray($response->json('errors.url'));
    }
}
