<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression coverage for issue #155: DEPLOYMENT.md's standalone/cloud
 * recipes (plain `docker run`, Heroku, Cloud Run, ECS) built the
 * `production` Dockerfile target and pointed a load balancer or `docker
 * run -p` straight at it. That target only runs php-fpm, which speaks
 * FastCGI on port 9000, not HTTP - none of those deployments could
 * actually serve a request. docker-compose works around this today only
 * because it runs a separate `nginx` service in front of the `app`
 * service's `production` container.
 *
 * The fix adds a `standalone` target that bundles nginx with php-fpm
 * behind supervisor so a single container can serve HTTP on port 80
 * directly, for platforms with no sidecar reverse proxy.
 *
 * Docker isn't available under the test suite, so this parses the
 * committed Dockerfile/config files and asserts the standalone target is
 * wired up correctly, catching a regression without needing to build an
 * image.
 */
class DockerfileStandaloneTargetTest extends TestCase
{
    public function test_standalone_target_builds_on_production_and_adds_nginx(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertNotFalse($dockerfile, 'Could not read Dockerfile');

        $this->assertMatchesRegularExpression(
            '/FROM production AS standalone/',
            $dockerfile,
            'The standalone target must build on top of the production target'
        );

        $standaloneStage = $this->extractStage($dockerfile, 'standalone');

        $this->assertStringContainsString(
            'nginx',
            $standaloneStage,
            'The standalone target must install nginx so the container can speak HTTP'
        );

        $this->assertMatchesRegularExpression(
            '/EXPOSE 80\b/',
            $standaloneStage,
            'The standalone target must expose port 80 for HTTP, not production\'s FastCGI port 9000'
        );

        $this->assertStringContainsString(
            'supervisord.standalone.conf',
            $standaloneStage,
            'The standalone target must use a supervisor config that also runs nginx'
        );
    }

    public function test_standalone_nginx_config_proxies_to_php_fpm_on_localhost(): void
    {
        $dockerfile = $this->extractStage(
            file_get_contents(base_path('Dockerfile')),
            'standalone'
        );

        $this->assertMatchesRegularExpression(
            '/fastcgi_pass 127\.0\.0\.1:9000;/',
            $dockerfile,
            'The standalone nginx config must proxy to php-fpm on localhost, '.
            'not the docker-compose-only "app" hostname'
        );
    }

    public function test_standalone_supervisor_config_runs_both_nginx_and_php_fpm(): void
    {
        $config = file_get_contents(base_path('docker/supervisord.standalone.conf'));

        $this->assertNotFalse($config, 'Could not read docker/supervisord.standalone.conf');

        $this->assertMatchesRegularExpression('/\[program:php-fpm\]/', $config);
        $this->assertMatchesRegularExpression('/\[program:nginx\]/', $config);
    }

    public function test_deployment_docs_no_longer_point_single_container_recipes_at_the_fastcgi_port(): void
    {
        $docs = file_get_contents(base_path('DEPLOYMENT.md'));

        $this->assertNotFalse($docs, 'Could not read DEPLOYMENT.md');

        $this->assertDoesNotMatchRegularExpression(
            '/-p 80:9000/',
            $docs,
            'DEPLOYMENT.md must not map a host port to php-fpm\'s FastCGI port - nothing on '.
            'the other end of a plain docker run can speak FastCGI'
        );

        $this->assertStringContainsString(
            'docker build --target standalone',
            $docs,
            'The single-container deployment recipes must build the standalone target'
        );
    }

    private function extractStage(string $dockerfile, string $stageName): string
    {
        $pattern = '/FROM \S+ AS '.preg_quote($stageName, '/').'\b(.*?)(?=\nFROM |\z)/s';

        $this->assertMatchesRegularExpression($pattern, $dockerfile, "Could not find the {$stageName} stage in the Dockerfile");

        preg_match($pattern, $dockerfile, $matches);

        return $matches[0];
    }
}
