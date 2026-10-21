<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

/**
 * Regression coverage for issue #153: Jetstream Teams was fully enabled
 * (team creation/invitations/switching, exposed in the nav) but no
 * application data was ever scoped by team — EndpointPolicy, EventPolicy,
 * and DeliveryPolicy only ever check individual user_id ownership. Inviting
 * a "team member" gave that user zero access to the inviter's resources,
 * so the feature was dead UI/DB weight that also added unused,
 * invitation-by-email attack surface. Teams is now disabled in
 * config/jetstream.php, matching the app's actual single-owner-per-resource
 * design, and its UI/routes should no longer be reachable.
 */
class TeamsFeatureDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_features_are_disabled(): void
    {
        $this->assertFalse(Features::hasTeamFeatures());
    }

    public function test_team_routes_are_not_registered(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/teams/create')->assertStatus(404);
        $this->post('/teams', ['name' => 'Test Team'])->assertStatus(404);
    }

    public function test_dashboard_does_not_expose_team_features_to_the_frontend(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('jetstream.hasTeamFeatures', false));
    }
}
