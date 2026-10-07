<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiStatusTest extends TestCase
{
    public function test_returns_the_public_server_status(): void
    {
        $response = $this->getJson('/api/v1/status');

        $response->assertOk()->assertExactJson([
            'name' => 'Motominator',
            'status' => 'ok',
        ]);
    }

    public function test_browser_clients_can_read_the_server_status(): void
    {
        $response = $this->getJson('/api/v1/status', [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertOk()->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
