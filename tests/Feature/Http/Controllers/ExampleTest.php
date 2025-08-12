<?php

namespace Tests\Feature\Http\Controllers;

use Tests\DatabaseTestCase;

class ExampleTest extends DatabaseTestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
