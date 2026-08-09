<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * Regression coverage for a fix to GET /health, which used to return a
 * full per-service breakdown (database/Redis connectivity, loaded PHP
 * extensions, queue heartbeat staleness) to any unauthenticated caller.
 * That leaked internal infrastructure details to the public internet
 * (see issue #106). /health is now a minimal, unauthenticated probe for
 * load balancers/orchestrators, and the detailed breakdown moved behind
 * a new authenticated /health/detailed route.
 *
 * Redis is mocked throughout (rather than requiring a live server) to
 * keep this test suite Redis-free, matching the rest of the suite.
 */
class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function fakeHealthyRedis(): void
    {
        Redis::shouldReceive('ping')->andReturn(true);
        Redis::shouldReceive('get')->with('queue:heartbeat')->andReturn((string) now()->timestamp);
    }

    public function test_public_health_endpoint_does_not_require_authentication(): void
    {
        $this->fakeHealthyRedis();

        $response = $this->getJson('/health');

        $response->assertStatus(200);
    }

    public function test_public_health_endpoint_only_exposes_overall_status(): void
    {
        $this->fakeHealthyRedis();

        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertExactJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'ok']);
    }

    public function test_public_health_endpoint_returns_503_without_service_details_when_degraded(): void
    {
        Redis::shouldReceive('ping')->andThrow(new \Exception('connection refused'));
        Redis::shouldReceive('get')->with('queue:heartbeat')->andReturn(null);

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertExactJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'error']);
    }

    public function test_detailed_health_endpoint_rejects_unauthenticated_requests(): void
    {
        $response = $this->getJson('/health/detailed');

        $response->assertStatus(401);
    }

    public function test_detailed_health_endpoint_returns_full_breakdown_for_authenticated_users(): void
    {
        $this->fakeHealthyRedis();
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'services' => ['database', 'redis', 'queue_worker'],
                'extensions' => ['pgsql', 'pdo_pgsql', 'redis'],
            ]);
    }

    public function test_detailed_health_endpoint_reports_stale_queue_worker(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Redis::shouldReceive('ping')->andReturn(true);
        Redis::shouldReceive('get')->with('queue:heartbeat')->andReturn((string) (now()->timestamp - 300));

        $response = $this->actingAs($user)->getJson('/health/detailed');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'services' => ['queue_worker' => 'stale'],
            ]);
    }
}
