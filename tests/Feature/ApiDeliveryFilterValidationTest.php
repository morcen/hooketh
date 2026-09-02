<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDeliveryFilterValidationTest extends TestCase
{
    use RefreshDatabase;

    private function createDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create(['name' => 'delivery.filter.test']);
        $endpoint = Endpoint::factory()->for($user)->create(['is_active' => true]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    public function test_invalid_from_date_returns_422_instead_of_500(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->createDelivery($user);

        $this->actingAs($user)
            ->getJson('/api/v1/deliveries?from_date=banana')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from_date');
    }

    public function test_invalid_to_date_returns_422_instead_of_500(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->createDelivery($user);

        $this->actingAs($user)
            ->getJson('/api/v1/deliveries?to_date=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to_date');
    }

    public function test_invalid_status_returns_422_instead_of_silently_empty_result(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->createDelivery($user);

        $this->actingAs($user)
            ->getJson('/api/v1/deliveries?status=not-a-real-status')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_valid_filters_still_return_matching_deliveries(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $delivery = $this->createDelivery($user);

        $this->actingAs($user)
            ->getJson('/api/v1/deliveries?status='.$delivery->status.'&from_date=2020-01-01&to_date=2099-01-01')
            ->assertOk()
            ->assertJsonFragment(['id' => $delivery->id]);
    }

    public function test_deprecated_alias_also_validates_filters(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->createDelivery($user);

        $this->actingAs($user)
            ->getJson('/api/deliveries?from_date=banana')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from_date');
    }
}
