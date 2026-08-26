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
        // The url column was left at the default VARCHAR(255), but both the
        // API and web EndpointController validate it as `max:2048`. Widen
        // the column to match the validation contract so a URL between 256
        // and 2048 characters no longer passes validation only to fail with
        // an unhandled DB error on insert/update.
        Schema::table('endpoints', function (Blueprint $table) {
            $table->string('url', 2048)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('endpoints', function (Blueprint $table) {
            $table->string('url', 255)->change();
        });
    }
};
