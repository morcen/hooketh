<?php

namespace Tests\Feature;

use App\Console\Commands\ProcessWebhookRetries;
use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendWebhookSoftDeletedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_fails_the_delivery_without_a_request_when_event_is_soft_deleted(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://8.8.8.8/webhook',
            'is_active' => true,
        ]);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $event->delete();

        (new SendWebhook($delivery))->handle();

        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Event was deleted.', $delivery->response_body);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_process_retries_does_not_deliver_a_delivery_whose_event_was_soft_deleted_while_awaiting_retry(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://8.8.8.8/webhook',
            'is_active' => true,
        ]);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $event->delete();

        $this->artisan(ProcessWebhookRetries::class)->assertExitCode(0);

        // The queued job runs synchronously under QUEUE_CONNECTION=sync, so by
        // the time the command returns, the retry attempt has already been
        // made (or, correctly, skipped) for the now-soft-deleted event.
        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Event was deleted.', $delivery->response_body);
        $this->assertNull($delivery->next_retry_at);
    }
}
