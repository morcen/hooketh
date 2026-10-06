<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Tests for issue #110: ownership checks used to be hand-duplicated per
 * controller method instead of centrally enforced by Laravel's
 * authorization framework. These tests exercise the EndpointPolicy,
 * EventPolicy, and DeliveryPolicy classes directly, including that a
 * denial reports as a 404 (not the framework's default 403) so a resource
 * owned by another user stays indistinguishable from one that doesn't
 * exist (see #102).
 */
class ResourcePolicyTest extends TestCase
{
    use RefreshDatabase;

    // -- EndpointPolicy ------------------------------------------------------

    public function test_endpoint_policy_allows_owner_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'update', 'delete', 'regenerateSecret', 'test'] as $ability) {
            $this->assertTrue($owner->can($ability, $endpoint), "Expected owner to be able to {$ability} the endpoint.");
        }
    }

    public function test_endpoint_policy_denies_non_owner_with_404_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'update', 'delete', 'regenerateSecret', 'test'] as $ability) {
            $response = Gate::forUser($other)->inspect($ability, $endpoint);

            $this->assertTrue($response->denied(), "Expected non-owner to be denied {$ability} on the endpoint.");
            $this->assertSame(404, $response->status());
        }
    }

    // -- EventPolicy -----------------------------------------------------

    public function test_event_policy_allows_owner_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'update', 'delete', 'syncEndpoints', 'trigger'] as $ability) {
            $this->assertTrue($owner->can($ability, $event), "Expected owner to be able to {$ability} the event.");
        }
    }

    public function test_event_policy_denies_non_owner_with_404_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $event = Event::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'update', 'delete', 'syncEndpoints', 'trigger'] as $ability) {
            $response = Gate::forUser($other)->inspect($ability, $event);

            $this->assertTrue($response->denied(), "Expected non-owner to be denied {$ability} on the event.");
            $this->assertSame(404, $response->status());
        }
    }

    // -- DeliveryPolicy --------------------------------------------------

    private function deliveryFor(User $owner): Delivery
    {
        $event = Event::factory()->create(['user_id' => $owner->id]);
        $endpoint = Endpoint::factory()->create(['user_id' => $owner->id]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
        ]);
    }

    public function test_delivery_policy_allows_owner_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $delivery = $this->deliveryFor($owner);

        foreach (['view', 'retry'] as $ability) {
            $this->assertTrue($owner->can($ability, $delivery), "Expected owner to be able to {$ability} the delivery.");
        }
    }

    public function test_delivery_policy_denies_non_owner_with_404_for_every_ability(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $other = User::factory()->withPersonalTeam()->create();
        $delivery = $this->deliveryFor($owner);

        foreach (['view', 'retry'] as $ability) {
            $response = Gate::forUser($other)->inspect($ability, $delivery);

            $this->assertTrue($response->denied(), "Expected non-owner to be denied {$ability} on the delivery.");
            $this->assertSame(404, $response->status());
        }
    }

    public function test_delivery_policy_still_allows_owner_after_parent_event_is_soft_deleted(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $delivery = $this->deliveryFor($owner);
        $delivery->event->delete();

        $this->assertTrue($owner->can('view', $delivery->fresh()));
        $this->assertTrue($owner->can('retry', $delivery->fresh()));
    }
}
