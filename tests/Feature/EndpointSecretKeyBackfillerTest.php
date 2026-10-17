<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EndpointSecretKeyBackfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for issue #142: the `encrypt_endpoint_secret_keys`
 * migration's chunked backfill logic had no dedicated test and couldn't be
 * exercised by `EndpointSecretKeyEncryptionTest`, because Feature tests run
 * every migration via `RefreshDatabase` against empty tables, so the
 * backfill's `chunkById` loop never actually processes a row in any test
 * run.
 *
 * The backfill logic now lives in `EndpointSecretKeyBackfiller` (used by
 * both the migration and this test), so it can be exercised directly
 * against rows seeded before it runs, bypassing the `Endpoint` model's
 * `encrypted` cast the same way the original plaintext data would have
 * predated it.
 */
class EndpointSecretKeyBackfillerTest extends TestCase
{
    use RefreshDatabase;

    public function test_encrypt_turns_plaintext_secret_keys_into_decryptable_ciphertext(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $endpointIds = collect(['plain-secret-one', 'plain-secret-two', 'plain-secret-three'])
            ->map(fn (string $plaintext) => DB::table('endpoints')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Endpoint',
                'url' => 'https://example.com/webhook',
                'secret_key' => $plaintext,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        app(EndpointSecretKeyBackfiller::class)->encrypt();

        $plaintexts = ['plain-secret-one', 'plain-secret-two', 'plain-secret-three'];

        foreach ($endpointIds->values() as $index => $id) {
            $rawValue = DB::table('endpoints')->where('id', $id)->value('secret_key');

            $this->assertNotSame($plaintexts[$index], $rawValue);
            $this->assertSame($plaintexts[$index], Crypt::decryptString($rawValue));
        }
    }

    public function test_decrypt_reverses_the_backfill_back_to_plaintext(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $id = DB::table('endpoints')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Endpoint',
            'url' => 'https://example.com/webhook',
            'secret_key' => Crypt::encryptString('already-encrypted-secret'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(EndpointSecretKeyBackfiller::class)->decrypt();

        $this->assertSame(
            'already-encrypted-secret',
            DB::table('endpoints')->where('id', $id)->value('secret_key')
        );
    }
}
