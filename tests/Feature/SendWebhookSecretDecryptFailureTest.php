<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendWebhookSecretDecryptFailureTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook', 'is_active' => true]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    /**
     * Rotates APP_KEY at runtime and rebuilds the 'encrypter' singleton so
     * the new config actually takes effect, mirroring what happens when an
     * operator changes APP_KEY and restarts the app.
     */
    private function rotateAppKey(string $newKey, array $previousKeys = []): void
    {
        config(['app.key' => $newKey, 'app.previous_keys' => $previousKeys]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_decrypt_exception_reading_endpoint_secret_is_caught_and_flagged_distinctly(): void
    {
        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        // Simulate a naive APP_KEY rotation: the secret was encrypted under
        // the old key, but the new key is set with no APP_PREVIOUS_KEYS, so
        // it can no longer be decrypted.
        $this->rotateAppKey('base64:'.base64_encode(Str::random(32)));

        $job = (new SendWebhook($delivery->fresh()))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertNull($delivery->response_code);
        $this->assertStringContainsString('decrypt', $delivery->response_body);
        $this->assertStringContainsString('APP_PREVIOUS_KEYS', $delivery->response_body);

        // Unlike a permanent 4xx rejection, this isn't inherently
        // unrecoverable (fixing APP_PREVIOUS_KEYS could make the next
        // attempt succeed), so it should still be scheduled for retry.
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_endpoint_secret_still_decrypts_after_app_key_rotation_via_previous_keys(): void
    {
        $originalKey = config('app.key');

        $endpoint = Endpoint::factory()->for(User::factory()->withPersonalTeam()->create())->create();
        $plainSecret = $endpoint->secret_key;

        // Rotate to a new key the proper way: the old key is preserved in
        // APP_PREVIOUS_KEYS, so values encrypted under it remain readable.
        $this->rotateAppKey('base64:'.base64_encode(Str::random(32)), [$originalKey]);

        $this->assertSame($plainSecret, $endpoint->fresh()->secret_key);
    }
}
