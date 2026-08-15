<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SoftDeletedEventDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function createDeliveryWithSoftDeletedEvent(User $user, array $deliveryState = []): Delivery
    {
        $event = Event::factory()->for($user)->create(['name' => 'soft-deleted-event-test']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);

        $delivery = Delivery::factory()->create(array_merge([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ], $deliveryState));

        $event->delete();

        return $delivery;
    }

    public function test_index_still_lists_deliveries_whose_event_was_soft_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDeliveryWithSoftDeletedEvent($user);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/deliveries')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($delivery->id));
    }

    public function test_stats_still_counts_deliveries_whose_event_was_soft_deleted(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->createDeliveryWithSoftDeletedEvent($user, ['status' => 'success']);

        $this->actingAs($user)
            ->getJson('/api/v1/deliveries/stats')
            ->assertOk()
            ->assertJson(['total' => 1, 'successful' => 1]);
    }

    public function test_show_returns_delivery_instead_of_500_after_event_soft_delete(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDeliveryWithSoftDeletedEvent($user);

        $this->actingAs($user)
            ->getJson("/api/v1/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('id', $delivery->id);
    }

    public function test_show_still_enforces_ownership_after_event_soft_delete(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDeliveryWithSoftDeletedEvent($owner);

        $this->actingAs($other)
            ->getJson("/api/v1/deliveries/{$delivery->id}")
            ->assertStatus(404);
    }

    public function test_retry_delivery_does_not_500_after_event_soft_delete(): void
    {
        Http::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDeliveryWithSoftDeletedEvent($user, ['status' => 'failed']);

        $this->actingAs($user)
            ->postJson("/api/v1/deliveries/{$delivery->id}/retry")
            ->assertOk();

        $this->assertSame('pending', $delivery->fresh()->status);
    }

    public function test_retry_delivery_still_enforces_ownership_after_event_soft_delete(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDeliveryWithSoftDeletedEvent($owner, ['status' => 'failed']);

        $this->actingAs($other)
            ->postJson("/api/v1/deliveries/{$delivery->id}/retry")
            ->assertStatus(404);
    }
}
