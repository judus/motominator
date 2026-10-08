<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DevelopmentToolsTest extends TestCase
{
    public function test_horizon_denies_guests_outside_local_development(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    public function test_horizon_denies_authenticated_users_outside_local_development(): void
    {
        $this->actingAs(User::factory()->make())
            ->get('/horizon')
            ->assertForbidden();
    }

    public function test_horizon_allows_verified_administrators_outside_local_development(): void
    {
        $this->actingAs(User::factory()->make(['is_admin' => true]))
            ->get('/horizon')->assertOk();
    }

    public function test_horizon_denies_unverified_administrators(): void
    {
        $this->actingAs(User::factory()->unverified()->make(['is_admin' => true]))
            ->get('/horizon')->assertForbidden();
    }

    public function test_horizon_is_available_in_local_development(): void
    {
        $this->app->instance('env', 'local');

        $this->get('/horizon')->assertOk();
    }

    public function test_telescope_routes_are_absent_outside_local_development(): void
    {
        $this->get('/telescope')->assertNotFound();
    }

    public function test_debugbar_routes_are_absent_outside_local_development(): void
    {
        $this->get('/_debugbar/assets')->assertNotFound();
    }
}
