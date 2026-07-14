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

class SendWebhookSsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_to_unsafe_url_is_blocked_without_making_a_request(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();

        // Bypasses controller validation to simulate a DNS-rebinding scenario
        // where the endpoint URL resolved safely at creation time but not now.
        $endpoint = Endpoint::factory()->for($user)->create([
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);

        (new SendWebhook($delivery))->handle();

        Http::assertNothingSent();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertStringContainsString('blocked', strtolower($delivery->response_body));
        $this->assertNotNull($delivery->next_retry_at);
    }

    public function test_delivery_to_safe_url_still_makes_a_request(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook']);

        $delivery = Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);

        (new SendWebhook($delivery))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'http://8.8.8.8/webhook');

        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
    }
}
