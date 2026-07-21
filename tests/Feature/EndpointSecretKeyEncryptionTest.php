<?php

namespace Tests\Feature;

use App\Models\Endpoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EndpointSecretKeyEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_key_is_stored_encrypted_at_rest(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'secret_key' => 'plain-text-secret-value',
        ]);

        $rawValue = DB::table('endpoints')->where('id', $endpoint->id)->value('secret_key');

        $this->assertNotSame('plain-text-secret-value', $rawValue);
        $this->assertSame('plain-text-secret-value', Crypt::decryptString($rawValue));
    }

    public function test_secret_key_attribute_transparently_decrypts_on_read(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create([
            'secret_key' => 'another-plain-secret',
        ]);

        $fresh = Endpoint::find($endpoint->id);

        $this->assertSame('another-plain-secret', $fresh->secret_key);
    }
}
