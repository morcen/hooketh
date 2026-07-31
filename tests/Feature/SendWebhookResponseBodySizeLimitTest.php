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

class SendWebhookResponseBodySizeLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_response_body_is_truncated_before_being_stored(): void
    {
        config(['webhooks.response_body_max_size' => 100]);

        Http::fake([
            '*' => Http::response(str_repeat('a', 500), 200),
        ]);

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

        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
        $this->assertStringStartsWith(str_repeat('a', 100), $delivery->response_body);
        $this->assertStringContainsString('truncated', $delivery->response_body);
        $this->assertLessThan(500, strlen($delivery->response_body));
    }

    public function test_response_body_within_configured_max_size_is_stored_verbatim(): void
    {
        config(['webhooks.response_body_max_size' => 100]);

        Http::fake([
            '*' => Http::response('short response', 200),
        ]);

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

        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
        $this->assertSame('short response', $delivery->response_body);
    }
}
