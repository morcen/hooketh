<?php

namespace Tests\Unit;

use Tests\TestCase;

class SessionSecureCookieConfigTest extends TestCase
{
    public function test_secure_cookie_defaults_to_true_when_app_url_is_https(): void
    {
        $this->assertTrue($this->resolveSecureCookieConfig('https://example.com', null));
    }

    public function test_secure_cookie_defaults_to_false_when_app_url_is_http(): void
    {
        $this->assertFalse($this->resolveSecureCookieConfig('http://example.com', null));
        $this->assertFalse($this->resolveSecureCookieConfig('http://localhost', null));
    }

    /**
     * Regression test for #120: the shipped docker-compose.yml stack (and the
     * quick-start `docker run` example in DEPLOYMENT.md) set APP_ENV=production
     * but never terminate TLS, so APP_URL stays http://. Secure cookies must
     * not be forced on in that case, or browsers silently drop the session
     * cookie and login appears to do nothing.
     */
    public function test_secure_cookie_defaults_to_false_in_production_without_tls(): void
    {
        $this->assertFalse($this->resolveSecureCookieConfig('http://localhost', null, appEnv: 'production'));
    }

    public function test_secure_cookie_defaults_to_true_in_production_behind_tls(): void
    {
        $this->assertTrue($this->resolveSecureCookieConfig('https://your-domain.com', null, appEnv: 'production'));
    }

    public function test_explicit_env_value_overrides_the_app_url_based_default(): void
    {
        $this->assertFalse($this->resolveSecureCookieConfig('https://example.com', 'false'));
        $this->assertTrue($this->resolveSecureCookieConfig('http://example.com', 'true'));
    }

    /**
     * Evaluate config/session.php's 'secure' entry under a given APP_URL /
     * SESSION_SECURE_COOKIE (and optionally APP_ENV) combination, restoring
     * the original values afterward.
     */
    private function resolveSecureCookieConfig(string $appUrl, ?string $secureCookieEnv, ?string $appEnv = null): mixed
    {
        $originalAppUrl = getenv('APP_URL');
        $originalSecureCookieEnv = getenv('SESSION_SECURE_COOKIE');
        $originalAppEnv = getenv('APP_ENV');

        $this->putOrClearEnv('APP_URL', $appUrl);
        $this->putOrClearEnv('SESSION_SECURE_COOKIE', $secureCookieEnv);

        if ($appEnv !== null) {
            $this->putOrClearEnv('APP_ENV', $appEnv);
        }

        $config = require base_path('config/session.php');

        $this->putOrClearEnv('APP_URL', $originalAppUrl === false ? null : $originalAppUrl);
        $this->putOrClearEnv('SESSION_SECURE_COOKIE', $originalSecureCookieEnv === false ? null : $originalSecureCookieEnv);

        if ($appEnv !== null) {
            $this->putOrClearEnv('APP_ENV', $originalAppEnv === false ? null : $originalAppEnv);
        }

        return $config['secure'];
    }

    private function putOrClearEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
