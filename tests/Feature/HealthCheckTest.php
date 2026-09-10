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

    public function test_public_health_endpoint_returns_503_when_redis_is_completely_down(): void
    {
        // Simulates a full Redis outage: both Redis::ping() and the later
        // Redis::get('queue:heartbeat') call throw, since a real
        // connection failure isn't limited to the first call that touches
        // the connection. Regression test for #76, where the second,
        // unguarded call bubbled up as an uncaught exception (raw 500)
        // instead of the intended structured 503.
        Redis::shouldReceive('ping')->andThrow(new \Exception('connection refused'));
        Redis::shouldReceive('get')->with('queue:heartbeat')->andThrow(new \Exception('connection refused'));

        $response = $this->getJson('/health');

        $response->assertStatus(503)
            ->assertExactJsonStructure(['status', 'timestamp'])
            ->assertJson(['status' => 'error']);
    }

    public function test_detailed_health_endpoint_returns_503_when_redis_is_completely_down(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        Redis::shouldReceive('ping')->andThrow(new \Exception('connection refused'));
        Redis::shouldReceive('get')->with('queue:heartbeat')->andThrow(new \Exception('connection refused'));

        $response = $this->actingAs($user)->getJson('/health/detailed');

        $response->assertStatus(503)
            ->assertJson([
                'status' => 'error',
                'services' => [
                    'database' => 'connected',
                    'redis' => 'disconnected',
                    'queue_worker' => 'unknown',
                ],
            ]);
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
                'extensions',
            ]);
    }

    public function test_detailed_health_endpoint_only_requires_extensions_for_the_configured_drivers(): void
    {
        // Regression test for #170: the extension check used to be a fixed
        // ['pgsql', 'pdo_pgsql', 'redis'] list regardless of the actually
        // configured DB_CONNECTION/REDIS_CLIENT, so a SQLite deployment
        // (this app's own documented default, and what the test suite
        // itself runs on) was checked against Postgres extensions it has
        // no use for. It should only report/require extensions relevant
        // to the configured drivers.
        config(['database.default' => 'sqlite', 'database.redis.client' => 'phpredis']);
        $this->fakeHealthyRedis();
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->getJson('/health/detailed');

        $response->assertOk()
            ->assertJsonPath('extensions.pdo_sqlite', true)
            ->assertJsonMissingPath('extensions.pgsql')
            ->assertJsonMissingPath('extensions.pdo_pgsql');
    }

    public function test_detailed_health_endpoint_does_not_require_the_redis_extension_for_a_predis_client(): void
    {
        // A `predis` client is a pure-PHP Redis library with no dependency
        // on the `redis` PHP extension, so it shouldn't be required (or
        // even reported) when that's the configured client.
        config(['database.redis.client' => 'predis']);
        $this->fakeHealthyRedis();
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($user)->getJson('/health/detailed');

        $response->assertOk()->assertJsonMissingPath('extensions.redis');
    }

    public function test_detailed_health_endpoint_requires_postgres_extensions_when_configured_for_pgsql(): void
    {
        // Create the user and fake Redis under the real (sqlite) test
        // connection first, then only flip config('database.default') to
        // 'pgsql' around the request itself and restore it immediately
        // after. Leaving it changed would make RefreshDatabase's
        // end-of-test rollback resolve the wrong ("pgsql") connection
        // instead of the sqlite one its transaction actually began on,
        // leaking a stuck transaction into every later test.
        $this->fakeHealthyRedis();
        $user = User::factory()->withPersonalTeam()->create();

        config(['database.default' => 'pgsql']);
        try {
            $response = $this->actingAs($user)->getJson('/health/detailed');
        } finally {
            config(['database.default' => 'sqlite']);
        }

        $response->assertJsonPath('extensions.pgsql', true)
            ->assertJsonPath('extensions.pdo_pgsql', true);
    }

    public function test_built_in_up_health_route_is_not_registered(): void
    {
        // Regression test for #158: Laravel's default `health: '/up'` route
        // used to coexist with this app's own /health endpoint. Unlike
        // /health, /up never checked the database, Redis, or the queue
        // heartbeat — it always returned 200. Wiring an orchestrator's
        // health probe to /up by convention would mask a real outage that
        // /health is specifically built to catch, so /up must not exist.
        $response = $this->getJson('/up');

        $response->assertStatus(404);
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
