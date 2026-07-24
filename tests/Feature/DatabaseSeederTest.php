<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_test_user_is_not_seeded_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_default_test_user_is_seeded_outside_production(): void
    {
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
