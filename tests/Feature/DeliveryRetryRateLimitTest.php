<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryRetryRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private static int $eventCounter = 0;

    private function createFailedDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create(['name' => 'delivery.retry.test.'.self::$eventCounter++]);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);

        return Delivery::factory()->failed()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    public function test_v1_api_delivery_retry_is_rate_limited(): void
    {
        Http::fake();
        config(['webhooks.rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();

        for ($i = 0; $i < 2; $i++) {
            $delivery = $this->createFailedDelivery($user);

            $this->actingAs($user)
                ->postJson("/api/v1/deliveries/{$delivery->id}/retry")
                ->assertOk();
        }

        $delivery = $this->createFailedDelivery($user);

        $this->actingAs($user)
            ->postJson("/api/v1/deliveries/{$delivery->id}/retry")
            ->assertStatus(429);
    }

    public function test_deprecated_api_delivery_retry_is_rate_limited(): void
    {
        Http::fake();
        config(['webhooks.rate_limit' => 2]);

        $user = User::factory()->withPersonalTeam()->create();

        for ($i = 0; $i < 2; $i++) {
            $delivery = $this->createFailedDelivery($user);

            $this->actingAs($user)
                ->postJson("/api/deliveries/{$delivery->id}/retry")
                ->assertOk();
        }

        $delivery = $this->createFailedDelivery($user);

        $this->actingAs($user)
            ->postJson("/api/deliveries/{$delivery->id}/retry")
            ->assertStatus(429);
    }

    public function test_delivery_retry_rate_limit_is_scoped_per_user(): void
    {
        Http::fake();
        config(['webhooks.rate_limit' => 1]);

        $userA = User::factory()->withPersonalTeam()->create();
        $userB = User::factory()->withPersonalTeam()->create();

        $deliveryA1 = $this->createFailedDelivery($userA);
        $deliveryA2 = $this->createFailedDelivery($userA);
        $deliveryB = $this->createFailedDelivery($userB);

        $this->actingAs($userA)
            ->postJson("/api/v1/deliveries/{$deliveryA1->id}/retry")
            ->assertOk();

        $this->actingAs($userA)
            ->postJson("/api/v1/deliveries/{$deliveryA2->id}/retry")
            ->assertStatus(429);

        $this->actingAs($userB)
            ->postJson("/api/v1/deliveries/{$deliveryB->id}/retry")
            ->assertOk();
    }
}
