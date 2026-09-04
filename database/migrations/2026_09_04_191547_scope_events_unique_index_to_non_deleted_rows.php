<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
        });

        // A plain unique index on (user_id, name) also matches soft-deleted
        // rows, permanently blocking reuse of a deleted event's name since
        // there is no restore path in the app. Scope the constraint to
        // non-deleted rows only via a partial index; this syntax is
        // supported identically by SQLite (tests) and PostgreSQL
        // (production), the two drivers this app runs on.
        DB::statement(
            'CREATE UNIQUE INDEX events_user_id_name_unique ON events (user_id, name) WHERE deleted_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX events_user_id_name_unique');

        Schema::table('events', function (Blueprint $table) {
            $table->unique(['user_id', 'name']);
        });
    }
};
