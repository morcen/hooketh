<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_not_seed_the_default_test_user_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_seeds_the_default_test_user_outside_production(): void
    {
        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
}
