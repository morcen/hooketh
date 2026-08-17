<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessWebhookRetriesOverlapProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeReadyForRetryDelivery(): Delivery
    {
        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create();

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);
    }

    /**
     * Guards against the scheduler starting a second, overlapping tick
     * while a prior run is still working through a retry backlog — the
     * scenario in which two ticks can both see the same rows as
     * status=failed and dispatch each of them twice.
     */
    public function test_scheduled_command_has_overlap_protection(): void
    {
        $schedule = app(Schedule::class);

        $event = collect($schedule->events())
            ->first(fn ($event) => str_contains($event->command, 'webhooks:process-retries'));

        $this->assertNotNull($event, 'webhooks:process-retries is not scheduled.');

        $this->assertTrue(
            $event->withoutOverlapping,
            'webhooks:process-retries must be scheduled with withoutOverlapping() so a slow run '
            .'(e.g. processing a large backlog) cannot overlap with the next scheduler tick.'
        );
    }

    /**
     * Regression test for the claim-then-dispatch loop: every delivery that
     * was ready for retry is dispatched exactly once, and is flipped out of
     * status=failed as part of the same atomic claim, so it can no longer be
     * matched by Delivery::readyForRetry() by the time dispatch happens.
     */
    public function test_each_ready_delivery_is_claimed_and_dispatched_exactly_once(): void
    {
        Queue::fake();

        $deliveries = collect(range(1, 3))->map(fn () => $this->makeReadyForRetryDelivery());

        $this->artisan('webhooks:process-retries')->assertSuccessful();

        foreach ($deliveries as $delivery) {
            $delivery->refresh();
            $this->assertSame('pending', $delivery->status);
            $this->assertNull($delivery->next_retry_at);

            Queue::assertPushed(
                SendWebhook::class,
                fn (SendWebhook $job) => $job->delivery->is($delivery)
            );
        }

        Queue::assertPushed(SendWebhook::class, $deliveries->count());
        $this->assertSame(0, Delivery::readyForRetry()->count());
    }

    /**
     * A duplicate/manual invocation of the command after a delivery has
     * already been claimed (flipped to `pending`) must not re-claim or
     * re-dispatch it — the atomic claim inside the transaction is what
     * protects against this even without the scheduler's own overlap guard.
     */
    public function test_a_delivery_already_claimed_by_one_run_is_not_claimed_again_by_another(): void
    {
        Queue::fake();

        $delivery = $this->makeReadyForRetryDelivery();

        $this->artisan('webhooks:process-retries')->assertSuccessful();
        $this->artisan('webhooks:process-retries')->assertSuccessful();

        Queue::assertPushed(SendWebhook::class, 1);
    }
}
