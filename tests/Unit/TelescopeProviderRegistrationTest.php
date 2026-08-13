<?php

namespace Tests\Unit;

use App\Providers\TelescopeServiceProvider;
use Tests\TestCase;

class TelescopeProviderRegistrationTest extends TestCase
{
    /**
     * laravel/telescope is a require-dev only dependency. If
     * TelescopeServiceProvider is listed directly in bootstrap/providers.php,
     * it is instantiated unconditionally on every request, which fatals with
     * a "class not found" error in any environment built without dev
     * dependencies (e.g. `composer install --no-dev`, as used by this app's
     * production Docker image). It must only ever be registered conditionally,
     * from a provider's register() method, guarded on the package actually
     * being installed.
     */
    public function test_telescope_service_provider_is_not_unconditionally_bootstrapped(): void
    {
        $providers = require base_path('bootstrap/providers.php');

        $this->assertNotContains(
            TelescopeServiceProvider::class,
            $providers,
            'TelescopeServiceProvider must not be listed in bootstrap/providers.php, since '.
            'that registers it unconditionally even when laravel/telescope (a require-dev '.
            'only package) is not installed.'
        );
    }

    /**
     * In any environment where the laravel/telescope package is actually
     * installed (e.g. local/dev, and this test suite), it should still be
     * registered so Telescope keeps working.
     */
    public function test_telescope_service_provider_is_registered_when_the_package_is_installed(): void
    {
        $this->assertTrue(
            class_exists(\Laravel\Telescope\TelescopeServiceProvider::class),
            'This test expects laravel/telescope to be installed as a dev dependency.'
        );

        $this->assertNotEmpty(
            $this->app->getProviders(TelescopeServiceProvider::class),
            'TelescopeServiceProvider should be registered when the laravel/telescope package is present.'
        );
    }
}
