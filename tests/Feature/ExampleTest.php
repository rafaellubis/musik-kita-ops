<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     * Unauthenticated user diarahkan ke login page (302).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        // Route / tidak authenticated, jadi redirect ke login (302)
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }
}
