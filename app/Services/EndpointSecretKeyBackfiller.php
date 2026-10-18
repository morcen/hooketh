<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Backfills the `endpoints.secret_key` column between its legacy plaintext
 * representation and the encrypted-at-rest representation the `Endpoint`
 * model's `encrypted` cast expects.
 *
 * Extracted out of the `encrypt_endpoint_secret_keys` migration so this
 * chunked read/re-encrypt logic can be exercised directly by a test against
 * seeded rows, independent of the migration runner (which Feature tests
 * only ever see run against empty tables via `RefreshDatabase`).
 */
class EndpointSecretKeyBackfiller
{
    public function encrypt(): void
    {
        DB::table('endpoints')->select('id', 'secret_key')->orderBy('id')->chunkById(100, function ($endpoints) {
            foreach ($endpoints as $endpoint) {
                DB::table('endpoints')->where('id', $endpoint->id)->update([
                    'secret_key' => Crypt::encryptString($endpoint->secret_key),
                ]);
            }
        });
    }

    public function decrypt(): void
    {
        DB::table('endpoints')->select('id', 'secret_key')->orderBy('id')->chunkById(100, function ($endpoints) {
            foreach ($endpoints as $endpoint) {
                DB::table('endpoints')->where('id', $endpoint->id)->update([
                    'secret_key' => Crypt::decryptString($endpoint->secret_key),
                ]);
            }
        });
    }
}
