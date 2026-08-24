<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The preceding migration now adds `event_type` as nullable directly,
        // so this is a redundant no-op on fresh installs. Kept in place so
        // databases that already ran it retain a consistent migration history.
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('event_type')->nullable(false)->change();
        });
    }
};
