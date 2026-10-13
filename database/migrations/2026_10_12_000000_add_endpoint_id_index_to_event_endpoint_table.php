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
        Schema::table('event_endpoint', function (Blueprint $table) {
            $table->index('endpoint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_endpoint', function (Blueprint $table) {
            $table->dropIndex(['endpoint_id']);
        });
    }
};
