<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for #47: prior ProcessWebhookRetries coverage
 * (#191, #195) only ever exercised a single delivery, or several identical
 * ones, per command run. This exercises one sweep processing several
 * deliveries that are each in a different state at once — one that
 * recovers and succeeds, one on its last allowed attempt that exhausts
 * permanently, and one that fails again but still has retries left — to
 * confirm the shared claim/dispatch loop handles each independently and
 * correctly rather than only ever having been proven against a uniform
 * batch.
 */
class ProcessWebhookRetriesMixedStateBatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyDelivery(User $user, string $url, int $attemptCount): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => $url, 'is_active' => true]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => $attemptCount,
            'next_retry_at' => now()->subMinute(),
        ]);
    }

    public function test_a_single_sweep_correctly_resolves_deliveries_in_different_states(): void
    {
        $maxAttempts = SendWebhook::maxAttempts();

        $user = User::factory()->withPersonalTeam()->create();

        // Literal public IPs rather than hostnames: SafeWebhookUrl's SSRF
        // check short-circuits on an IP literal (no DNS resolution needed),
        // keeping this test deterministic with no live network dependency.
        $recovering = $this->makeReadyDelivery($user, 'http://8.8.8.8/webhook', 1);
        $exhausting = $this->makeReadyDelivery($user, 'http://1.1.1.1/webhook', $maxAttempts - 1);
        $retryingAgain = $this->makeReadyDelivery($user, 'http://9.9.9.9/webhook', 1);

        Http::fake(function (ClientRequest $request) {
            return str_contains($request->url(), '8.8.8.8')
                ? Http::response('ok', 200)
                : Http::response('error', 500);
        });

        $this->artisan('webhooks:process-retries')->assertSuccessful();

        $recovering->refresh();
        $this->assertSame('success', $recovering->status);
        $this->assertNotNull($recovering->delivered_at);
        $this->assertNull($recovering->next_retry_at);

        $exhausting->refresh();
        $this->assertSame('failed', $exhausting->status);
        $this->assertSame($maxAttempts, $exhausting->attempt_count);
        $this->assertNull(
            $exhausting->next_retry_at,
            'A delivery on its final attempt must not be left matching scopeReadyForRetry() forever.'
        );

        $retryingAgain->refresh();
        $this->assertSame('failed', $retryingAgain->status);
        $this->assertSame(2, $retryingAgain->attempt_count);
        $this->assertNotNull(
            $retryingAgain->next_retry_at,
            'A delivery with attempts remaining must be rescheduled rather than exhausted.'
        );

        // Only the still-flaky deliveries should remain pickupable by a
        // later sweep; the recovered one is done and the exhausted one is
        // permanently failed.
        $this->assertSame(0, Delivery::readyForRetry()->whereKey($recovering->id)->count());
        $this->assertSame(0, Delivery::readyForRetry()->whereKey($exhausting->id)->count());
    }
}
