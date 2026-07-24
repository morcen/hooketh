<?php

namespace Tests\Unit;

use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\Telescope;
use ReflectionMethod;
use Tests\TestCase;

class TelescopeServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->resetTelescopeHiddenLists();

        parent::tearDown();
    }

    public function test_authorization_header_is_hidden_from_telescope_entries(): void
    {
        $this->invokeHideSensitiveRequestDetails();

        $this->assertContains('authorization', Telescope::$hiddenRequestHeaders);
    }

    public function test_password_fields_are_hidden_from_telescope_entries(): void
    {
        $this->invokeHideSensitiveRequestDetails();

        $this->assertContains('password', Telescope::$hiddenRequestParameters);
        $this->assertContains('password_confirmation', Telescope::$hiddenRequestParameters);
    }

    public function test_plain_webhook_secret_is_hidden_from_telescope_response_entries(): void
    {
        $this->invokeHideSensitiveRequestDetails();

        $this->assertContains('plain_secret', Telescope::$hiddenResponseParameters);
        $this->assertContains('secret_key', Telescope::$hiddenResponseParameters);
    }

    public function test_sensitive_details_are_hidden_even_in_the_local_environment(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        $this->invokeHideSensitiveRequestDetails();

        $this->assertContains('authorization', Telescope::$hiddenRequestHeaders);
        $this->assertContains('password', Telescope::$hiddenRequestParameters);
        $this->assertContains('plain_secret', Telescope::$hiddenResponseParameters);
    }

    private function invokeHideSensitiveRequestDetails(): void
    {
        $this->resetTelescopeHiddenLists();

        $provider = new TelescopeServiceProvider($this->app);

        $method = new ReflectionMethod($provider, 'hideSensitiveRequestDetails');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    private function resetTelescopeHiddenLists(): void
    {
        Telescope::$hiddenRequestHeaders = [];
        Telescope::$hiddenRequestParameters = [];
        Telescope::$hiddenResponseParameters = [];
    }
}
