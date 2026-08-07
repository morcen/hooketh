<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Regression coverage for a fix to docker-compose.yml where DB/Redis
 * credentials were hardcoded under each service's `environment:` key.
 * Compose gives `environment:` precedence over `env_file:`, so those
 * literals silently overrode whatever DB_PASSWORD/REDIS_PASSWORD a user
 * set in their own .env file.
 *
 * These assertions can't spin up docker-compose, so they parse the
 * committed file and confirm the credential-bearing values are sourced
 * from the host environment (via ${VAR} substitution) rather than
 * hardcoded literals, so a regression is caught without needing Docker.
 */
class DockerComposeCredentialsTest extends TestCase
{
    private const HARDCODED_DB_PASSWORD = 'webhook_password';

    private const HARDCODED_REDIS_PASSWORD = 'webhook_redis_password';

    public function test_postgres_service_password_is_sourced_from_env_var(): void
    {
        $config = $this->parseDockerCompose();

        $password = $config['services']['db']['environment']['POSTGRES_PASSWORD'];

        $this->assertStringContainsString('${DB_PASSWORD', (string) $password);
        $this->assertNotSame(self::HARDCODED_DB_PASSWORD, $password);
    }

    public function test_redis_service_requirepass_is_sourced_from_env_var(): void
    {
        $config = $this->parseDockerCompose();

        $command = $config['services']['redis']['command'];

        $this->assertStringContainsString('${REDIS_PASSWORD', $command);
        $this->assertNotSame('redis-server --appendonly yes --requirepass '.self::HARDCODED_REDIS_PASSWORD, $command);
    }

    public function test_app_queue_and_scheduler_services_source_credentials_from_env_vars(): void
    {
        $config = $this->parseDockerCompose();

        foreach (['app', 'queue', 'scheduler'] as $service) {
            $env = $this->environmentListToMap($config['services'][$service]['environment']);

            $this->assertArrayHasKey('DB_PASSWORD', $env, "Missing DB_PASSWORD for the {$service} service");
            $this->assertStringContainsString('${DB_PASSWORD', $env['DB_PASSWORD']);
            $this->assertNotSame(self::HARDCODED_DB_PASSWORD, $env['DB_PASSWORD']);

            $this->assertArrayHasKey('REDIS_PASSWORD', $env, "Missing REDIS_PASSWORD for the {$service} service");
            $this->assertStringContainsString('${REDIS_PASSWORD', $env['REDIS_PASSWORD']);
            $this->assertNotSame(self::HARDCODED_REDIS_PASSWORD, $env['REDIS_PASSWORD']);
        }
    }

    private function parseDockerCompose(): array
    {
        return Yaml::parseFile(base_path('docker-compose.yml'));
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
