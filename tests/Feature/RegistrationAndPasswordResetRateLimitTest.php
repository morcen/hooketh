<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class RegistrationAndPasswordResetRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function registrationPayload(): array
    {
        return [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? 'on' : null,
        ];
    }

    public function test_registration_is_rate_limited(): void
    {
        if (! Features::enabled(Features::registration())) {
            $this->markTestSkipped('Registration is not enabled.');
        }

        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->registrationPayload());
        }

        $this->post('/register', $this->registrationPayload())->assertStatus(429);
    }

    public function test_password_reset_link_request_is_rate_limited(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        Notification::fake();

        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => $user->email]);
        }

        $this->post('/forgot-password', ['email' => $user->email])->assertStatus(429);
    }

    public function test_password_reset_is_rate_limited(): void
    {
        if (! Features::enabled(Features::resetPasswords())) {
            $this->markTestSkipped('Password updates are not enabled.');
        }

        $user = User::factory()->create();

        $payload = [
            'token' => 'not-a-valid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/reset-password', $payload);
        }

        $this->post('/reset-password', $payload)->assertStatus(429);
    }
}
