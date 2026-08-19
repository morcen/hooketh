<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use TypeError;

class SendWebhookThrowableHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook']);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    public function test_fatal_error_during_delivery_is_caught_and_marks_delivery_failed(): void
    {
        // A TypeError (or any other \Error) is not an \Exception. Prior to
        // widening handle()'s catch block to \Throwable, this would propagate
        // out of the job uncaught instead of being recorded as a failure.
        Http::fake(function () {
            throw new TypeError('Simulated fatal type error.');
        });

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Simulated fatal type error.', $delivery->response_body);
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_failed_callback_accepts_a_throwable_and_updates_delivery(): void
    {
        // Laravel's queue worker invokes failed() with whatever \Throwable
        // ultimately failed the job, which includes \Error subclasses. Prior
        // to widening failed()'s parameter type to \Throwable, passing a
        // TypeError here raised a new, unrelated TypeError instead of
        // recording the original failure.
        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());
        $delivery->update(['next_retry_at' => now()->addMinute()]);

        $job = new SendWebhook($delivery);
        $job->failed(new TypeError('Simulated unrecoverable type error.'));

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Simulated unrecoverable type error.', $delivery->response_body);
        $this->assertNull($delivery->next_retry_at);
    }
}
