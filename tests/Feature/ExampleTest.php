<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the dashboard.
     */
    public function test_the_application_redirects_to_the_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
