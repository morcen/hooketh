<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessWebhookRetriesStuckRetryingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a delivery in `retrying` status and force its `updated_at`
     * to an arbitrary time in the past, simulating a queue worker that
     * crashed or was restarted mid-attempt (SendWebhook::handle() sets
     * status to `retrying` before any terminal success/failed update).
     */
    private function makeStuckRetryingDelivery(int $attemptCount, int $minutesStuck): Delivery
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'retrying',
            'attempt_count' => $attemptCount,
            'next_retry_at' => null,
        ]);

        DB::table('deliveries')->where('id', $delivery->id)->update([
            'updated_at' => now()->subMinutes($minutesStuck)->toDateTimeString(),
        ]);

        return $delivery->refresh();
    }

    public function test_stuck_retrying_delivery_within_retry_limit_is_recovered_and_requeued(): void
    {
        Queue::fake();

        $maxAttempts = SendWebhook::maxAttempts();
        $delivery = $this->makeStuckRetryingDelivery(attemptCount: $maxAttempts - 1, minutesStuck: 15);

        $this->artisan('webhooks:process-retries')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('pending', $delivery->status);
        $this->assertNull($delivery->next_retry_at);

        Queue::assertPushed(SendWebhook::class, fn (SendWebhook $job) => $job->delivery->is($delivery));
    }

    public function test_stuck_retrying_delivery_that_exhausted_retries_is_marked_permanently_failed(): void
    {
        Queue::fake();

        $maxAttempts = SendWebhook::maxAttempts();
        $delivery = $this->makeStuckRetryingDelivery(attemptCount: $maxAttempts, minutesStuck: 15);

        $this->artisan('webhooks:process-retries')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertNull($delivery->next_retry_at);

        Queue::assertNotPushed(SendWebhook::class);
    }

    public function test_recently_retrying_delivery_below_the_stuck_threshold_is_left_alone(): void
    {
        Queue::fake();

        $delivery = $this->makeStuckRetryingDelivery(attemptCount: 1, minutesStuck: 1);

        $this->artisan('webhooks:process-retries')->assertSuccessful();

        $delivery->refresh();
        $this->assertSame('retrying', $delivery->status);

        Queue::assertNotPushed(SendWebhook::class);
    }
}
