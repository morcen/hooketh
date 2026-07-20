<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
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

        DB::table('endpoints')->select('id', 'secret_key')->orderBy('id')->chunkById(100, function ($endpoints) {
            foreach ($endpoints as $endpoint) {
                DB::table('endpoints')->where('id', $endpoint->id)->update([
                    'secret_key' => Crypt::encryptString($endpoint->secret_key),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('endpoints')->select('id', 'secret_key')->orderBy('id')->chunkById(100, function ($endpoints) {
            foreach ($endpoints as $endpoint) {
                DB::table('endpoints')->where('id', $endpoint->id)->update([
                    'secret_key' => Crypt::decryptString($endpoint->secret_key),
                ]);
            }
        });

        Schema::table('endpoints', function (Blueprint $table) {
            $table->string('secret_key')->change();
        });
    }
};
