<?php

use App\Services\EndpointSecretKeyBackfiller;
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
        // Encrypted values are significantly longer than the plaintext secret,
        // so the column needs room before we backfill it below.
        Schema::table('endpoints', function (Blueprint $table) {
            $table->text('secret_key')->change();
        });

        app(EndpointSecretKeyBackfiller::class)->encrypt();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(EndpointSecretKeyBackfiller::class)->decrypt();

        Schema::table('endpoints', function (Blueprint $table) {
            $table->string('secret_key')->change();
        });
    }
};
