<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventTypeColumnMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for the "add_event_type_and_payload_to_events_table"
     * migration failing with a NOT NULL constraint violation when run
     * against an `events` table that already has rows (e.g. a staging DB
     * cloned/seeded from production, or a database re-migrated from an
     * older snapshot). See issue #118.
     */
    public function test_event_type_migration_succeeds_against_a_populated_events_table(): void
    {
        $user = User::factory()->create();

        // Recreate the `events` table exactly as it existed before the
        // event_type/payload migration ever ran (i.e. the original
        // create_events_table schema), then populate it with a row —
        // simulating a real database that predates this migration.
        Schema::dropIfExists('events');
        Schema::create('events', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        DB::table('events')->insert([
            'user_id' => $user->id,
            'name' => 'pre_existing.event',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2025_08_25_110616_add_event_type_and_payload_to_events_table.php');

        // Must not throw a NOT NULL constraint violation against the
        // already-populated table.
        $migration->up();

        $this->assertTrue(Schema::hasColumn('events', 'event_type'));
        $this->assertDatabaseHas('events', [
            'name' => 'pre_existing.event',
            'event_type' => null,
        ]);
    }
}
