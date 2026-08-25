<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Nullable from the start: a NOT NULL column with no default fails
            // this migration against any events table that already has rows
            // (see the follow-up migration below, which used to be the only
            // place this got relaxed — too late to help a populated table).
            $table->string('event_type')->nullable()->after('name');
            $table->json('payload')->nullable()->after('event_type');

            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn(['event_type', 'payload']);
        });
    }
};
