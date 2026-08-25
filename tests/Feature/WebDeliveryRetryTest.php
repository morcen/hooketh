<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebDeliveryRetryTest extends TestCase
{
    use RefreshDatabase;

    private static int $eventCounter = 0;

    private function createDelivery(User $user, array $deliveryState = []): Delivery
    {
        $event = Event::factory()->for($user)->create(['name' => 'web.delivery.retry.test.'.self::$eventCounter++]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);

        return Delivery::factory()->create(array_merge([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ], $deliveryState));
    }

    public function test_retry_button_route_exists_and_retries_a_failed_delivery(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDelivery($user, ['status' => 'failed']);

        // Prior to this fix, `deliveries.retry` had no matching web route, so the
        // Vue page's retry button threw an uncaught Ziggy exception and never
        // reached the server at all.
        $this->actingAs($user)
            ->post(route('deliveries.retry', $delivery))
            ->assertRedirect();

        $this->assertSame('pending', $delivery->fresh()->status);
    }

    public function test_retry_enforces_ownership(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDelivery($owner, ['status' => 'failed']);

        $this->actingAs($other)
            ->post(route('deliveries.retry', $delivery))
            ->assertStatus(404);

        $this->assertSame('failed', $delivery->fresh()->status);
    }

    public function test_retry_rejects_a_delivery_that_is_not_failed(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDelivery($user, ['status' => 'success']);

        $this->actingAs($user)
            ->post(route('deliveries.retry', $delivery))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('success', $delivery->fresh()->status);
    }

    public function test_retry_failed_button_route_exists_and_retries_only_the_users_failed_deliveries(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->withPersonalTeam()->create();
        $otherUser = User::factory()->withPersonalTeam()->create();

        $failedA = $this->createDelivery($user, ['status' => 'failed']);
        $failedB = $this->createDelivery($user, ['status' => 'failed']);
        $successful = $this->createDelivery($user, ['status' => 'success']);
        $othersFailed = $this->createDelivery($otherUser, ['status' => 'failed']);

        // Prior to this fix, `deliveries.retry-failed` had no matching web
        // route/controller anywhere, so this button silently did nothing.
        $this->actingAs($user)
            ->post(route('deliveries.retry-failed'))
            ->assertRedirect();

        $this->assertSame('pending', $failedA->fresh()->status);
        $this->assertSame('pending', $failedB->fresh()->status);
        $this->assertSame('success', $successful->fresh()->status);
        $this->assertSame('failed', $othersFailed->fresh()->status);
    }
}
