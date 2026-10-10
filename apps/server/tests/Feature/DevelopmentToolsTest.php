<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DevelopmentToolsTest extends TestCase
{
    public function testHorizonDeniesGuestsOutsideLocalDevelopment(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    public function testHorizonDeniesAuthenticatedUsersOutsideLocalDevelopment(): void
    {
        $this->actingAs(User::factory()->make())
            ->get('/horizon')
            ->assertForbidden();
    }

    public function testHorizonAllowsVerifiedAdministratorsOutsideLocalDevelopment(): void
    {
        $this->actingAs(User::factory()->make(['is_admin' => true]))
            ->get('/horizon')->assertOk();
    }

    public function testHorizonDeniesUnverifiedAdministrators(): void
    {
        $this->actingAs(User::factory()->unverified()->make(['is_admin' => true]))
            ->get('/horizon')->assertForbidden();
    }

    public function testHorizonRequiresAVerifiedAdministratorInLocalDevelopment(): void
    {
        $this->app->instance('env', 'local');

        $this->get('/horizon')->assertForbidden();
        $this->actingAs(User::factory()->make(['is_admin' => true]))
            ->get('/horizon')->assertOk();
    }

    public function testTelescopeRoutesAreAbsentOutsideLocalDevelopment(): void
    {
        $this->get('/telescope')->assertNotFound();
    }

    public function testDebugbarRoutesAreAbsentOutsideLocalDevelopment(): void
    {
        $this->get('/_debugbar/assets')->assertNotFound();
    }
}
