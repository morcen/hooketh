<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ForceHttpsMiddlewareTest extends TestCase
{
    private function fakeHealthyRedis(): void
    {
        Redis::shouldReceive('ping')->andReturn(true);
        Redis::shouldReceive('get')->with('queue:heartbeat')->andReturn((string) now()->timestamp);
    }

    public function test_insecure_request_is_redirected_to_https_when_force_https_is_enabled(): void
    {
        config(['app.force_https' => true]);

        $response = $this->get('/health');

        $response->assertRedirect('https://localhost/health');
        $response->assertStatus(301);
    }

    public function test_insecure_request_passes_through_when_force_https_is_disabled(): void
    {
        config(['app.force_https' => false]);
        $this->fakeHealthyRedis();

        $response = $this->get('/health');

        $response->assertOk();
    }

    public function test_already_secure_request_is_not_redirected_when_force_https_is_enabled(): void
    {
        config(['app.force_https' => true]);
        $this->fakeHealthyRedis();

        $response = $this->get('https://localhost/health', ['HTTPS' => 'on']);

        $response->assertOk();
    }
}
