<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Features;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_accounts_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $this->delete('/user', [
            'password' => 'password',
        ]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_correct_password_must_be_provided_before_account_can_be_deleted(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $this->actingAs($user = User::factory()->create());

        $this->delete('/user', [
            'password' => 'wrong-password',
        ]);

        $this->assertNotNull($user->fresh());
    }

    public function test_deleting_account_preserves_endpoint_event_and_delivery_history(): void
    {
        if (! Features::hasAccountDeletionFeatures()) {
            $this->markTestSkipped('Account deletion is not enabled.');
        }

        $user = User::factory()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();
        $delivery = Delivery::factory()->for($event)->for($endpoint)->create();

        $this->actingAs($user);

        $this->delete('/user', [
            'password' => 'password',
        ]);

        // The user row itself is soft-deleted, not physically removed, so the
        // database-level ON DELETE CASCADE from users -> endpoints/events
        // (and onward to deliveries) never fires.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('endpoints', ['id' => $endpoint->id]);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id]);
    }
}
