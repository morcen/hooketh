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

class SendWebhookInactiveEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_handle_fails_the_delivery_without_a_request_when_endpoint_is_inactive(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://8.8.8.8/webhook',
            'is_active' => false,
        ]);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        (new SendWebhook($delivery))->handle();

        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame('Endpoint is inactive.', $delivery->response_body);
        $this->assertNull($delivery->next_retry_at);
    }

    public function test_process_retries_does_not_deliver_to_an_endpoint_deactivated_while_awaiting_retry(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://8.8.8.8/webhook',
            'is_active' => false,
        ]);

        Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'failed',
            'attempt_count' => 1,
            'next_retry_at' => now()->subMinute(),
        ]);

        $this->artisan(ProcessWebhookRetries::class)->assertExitCode(0);

        // The queued job runs synchronously under QUEUE_CONNECTION=sync, so by
        // the time the command returns, the retry attempt has already been
        // made (or, correctly, skipped) against the deactivated endpoint.
        Http::assertNothingSent();
    }
}
