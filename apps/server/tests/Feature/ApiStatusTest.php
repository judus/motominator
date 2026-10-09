<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiStatusTest extends TestCase
{
    public function testReturnsThePublicServerStatus(): void
    {
        $response = $this->getJson('/api/v1/status');

        $response->assertOk()->assertExactJson([
            'name' => 'Motominator',
            'status' => 'ok',
        ]);
    }

    public function testBrowserClientsCanReadTheServerStatus(): void
    {
        $response = $this->getJson('/api/v1/status', [
            'Origin' => 'http://localhost:5173',
        ]);

        $response->assertOk()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
