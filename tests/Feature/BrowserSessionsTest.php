<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrowserSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_other_browser_sessions_can_be_logged_out(): void
    {
        // Jetstream's OtherBrowserSessionsController only deletes session
        // rows when the database session driver is active; the test suite
        // otherwise runs with SESSION_DRIVER=array, under which this
        // feature is a no-op.
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->actingAs($user);

        DB::table('sessions')->insert([
            'id' => 'other-browser-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->delete('/user/other-browser-sessions', [
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertStatus(303);

        $this->assertDatabaseMissing('sessions', ['id' => 'other-browser-session-id']);
    }

    public function test_other_browser_sessions_logout_requires_correct_password(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create();
        $this->actingAs($user);

        DB::table('sessions')->insert([
            'id' => 'other-browser-session-id',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other Browser',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->delete('/user/other-browser-sessions', [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');

        $this->assertDatabaseHas('sessions', ['id' => 'other-browser-session-id']);
    }
}
