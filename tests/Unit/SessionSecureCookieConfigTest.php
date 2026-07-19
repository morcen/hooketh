<?php

namespace Tests\Unit;

use Tests\TestCase;

class SessionSecureCookieConfigTest extends TestCase
{
    public function test_secure_cookie_defaults_to_true_in_production(): void
    {
        $this->assertTrue($this->resolveSecureCookieConfig('production', null));
    }

    public function test_secure_cookie_defaults_to_false_outside_production(): void
    {
        $this->assertFalse($this->resolveSecureCookieConfig('local', null));
        $this->assertFalse($this->resolveSecureCookieConfig('testing', null));
    }

    public function test_explicit_env_value_overrides_the_environment_based_default(): void
    {
        $this->assertFalse($this->resolveSecureCookieConfig('production', 'false'));
        $this->assertTrue($this->resolveSecureCookieConfig('local', 'true'));
    }

    /**
     * Evaluate config/session.php's 'secure' entry under a given APP_ENV /
     * SESSION_SECURE_COOKIE combination, restoring the original values afterward.
     */
    private function resolveSecureCookieConfig(string $appEnv, ?string $secureCookieEnv): mixed
    {
        $originalAppEnv = getenv('APP_ENV');
        $originalSecureCookieEnv = getenv('SESSION_SECURE_COOKIE');

        $this->putOrClearEnv('APP_ENV', $appEnv);
        $this->putOrClearEnv('SESSION_SECURE_COOKIE', $secureCookieEnv);

        $config = require base_path('config/session.php');

        $this->putOrClearEnv('APP_ENV', $originalAppEnv === false ? null : $originalAppEnv);
        $this->putOrClearEnv('SESSION_SECURE_COOKIE', $originalSecureCookieEnv === false ? null : $originalSecureCookieEnv);

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
