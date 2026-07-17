<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Unauthenticated visitors of `/` are redirected to login (302), not
        // served a 200 — this app has no public landing page.
        $response->assertStatus(302);
    }
}
