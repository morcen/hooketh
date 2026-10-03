<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Regression coverage for a fix where shipped deployment configs set the
 * Laravel 10-era `CACHE_DRIVER` env var, which `config/cache.php` no longer
 * reads (Laravel 11+ reads `CACHE_STORE`). Setting the wrong name meant
 * every documented deployment path silently fell back to the `database`
 * cache store instead of Redis, with no error or warning.
 *
 * These assertions parse the committed files directly so the mismatch is
 * caught without needing to actually run docker-compose or deploy.
 */
class CacheStoreEnvVarTest extends TestCase
{
    public function test_docker_compose_app_service_sets_cache_store_not_cache_driver(): void
    {
        $config = Yaml::parseFile(base_path('docker-compose.yml'));

        $env = $this->environmentListToMap($config['services']['app']['environment']);

        $this->assertArrayHasKey('CACHE_STORE', $env, 'app service is missing CACHE_STORE');
        $this->assertSame('redis', $env['CACHE_STORE']);
        $this->assertArrayNotHasKey('CACHE_DRIVER', $env, 'app service still sets the stale CACHE_DRIVER var');
    }

    public function test_laravel_cloud_config_sets_cache_store_not_cache_driver(): void
    {
        $config = Yaml::parseFile(base_path('.laravelcloud.yml'));

        $this->assertArrayHasKey('CACHE_STORE', $config['environment'], '.laravelcloud.yml is missing CACHE_STORE');
        $this->assertSame('redis', $config['environment']['CACHE_STORE']);
        $this->assertArrayNotHasKey('CACHE_DRIVER', $config['environment'], '.laravelcloud.yml still sets the stale CACHE_DRIVER var');
    }

    public function test_cache_config_reads_cache_store_not_cache_driver(): void
    {
        $contents = file_get_contents(base_path('config/cache.php'));

        $this->assertStringContainsString("env('CACHE_STORE'", $contents);
        $this->assertStringNotContainsString("env('CACHE_DRIVER'", $contents);
    }

    private function environmentListToMap(array $environment): array
    {
        $map = [];

        foreach ($environment as $entry) {
            [$key, $value] = explode('=', $entry, 2);
            $map[$key] = $value;
        }

        return $map;
    }
}
