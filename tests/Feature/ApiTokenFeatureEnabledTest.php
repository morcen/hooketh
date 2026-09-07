<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class ApiTokenFeatureEnabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_jetstream_api_feature_is_enabled(): void
    {
        $this->assertTrue(
            Features::hasApiFeatures(),
            'Jetstream\'s Features::api() must stay enabled, otherwise the documented '.
            'REST API has no way to issue tokens (see issue #93).'
        );
    }

    public function test_authenticated_user_can_reach_the_api_tokens_page(): void
    {
        $this->actingAs($user = User::factory()->withPersonalTeam()->create());

        $response = $this->get('/user/api-tokens');

        $response->assertOk();
    }
}
