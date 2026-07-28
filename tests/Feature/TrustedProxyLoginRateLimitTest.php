<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxyLoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY_IP = '10.0.0.1';

    protected function setUp(): void
    {
        putenv('TRUSTED_PROXIES='.self::PROXY_IP);
        $_ENV['TRUSTED_PROXIES'] = self::PROXY_IP;
        $_SERVER['TRUSTED_PROXIES'] = self::PROXY_IP;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('TRUSTED_PROXIES');
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);

        parent::tearDown();
    }

    private function attemptLogin(string $email, string $forwardedFor)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY_IP])
            ->withHeaders(['X-Forwarded-For' => $forwardedFor])
            ->post('/login', [
                'email' => $email,
                'password' => 'wrong-password',
            ]);
    }

    public function test_login_rate_limiter_keys_on_the_forwarded_client_ip_behind_a_trusted_proxy(): void
    {
        $user = User::factory()->create(['email' => 'victim@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->attemptLogin($user->email, '203.0.113.10');
        }

        // The attacker's own forwarded IP is now throttled.
        $this->attemptLogin($user->email, '203.0.113.10')->assertStatus(429);

        // A different real client IP behind the same trusted proxy is unaffected,
        // proving the limiter is keyed on the forwarded client IP, not the
        // shared proxy IP an attacker could otherwise collapse everyone onto.
        $this->attemptLogin($user->email, '198.51.100.20')->assertStatus(302);
        $this->assertGuest();
    }
}
