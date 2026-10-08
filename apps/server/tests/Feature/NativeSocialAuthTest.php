<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NativeSocialAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
    }

    private function begin(): string
    {
        return $this->postJson('/api/v1/auth/native', ['provider' => 'google', 'device_name' => 'Android', 'challenge' => hash('sha256', str_repeat('v', 64))])->assertCreated()->json('code');
    }

    public function test_native_handoff_requires_browser_authorization_and_a_matching_verifier(): void
    {
        $code = $this->begin();
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => str_repeat('v', 64)])->assertUnprocessable();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['native.code' => $code, 'native.user_id' => $user->id])
            ->get('/auth/mobile/complete')->assertRedirect('motominator://auth-return?code='.$code);
        Auth::forgetGuards();
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => str_repeat('x', 64)])->assertUnprocessable();
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => str_repeat('v', 64)])->assertCreated()->assertJsonStructure(['token', 'expires_at']);
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => str_repeat('v', 64)])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_expired_handoff_cannot_issue_a_token(): void
    {
        $this->freezeTime();
        $code = $this->begin();
        $this->travel(6)->minutes();
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => str_repeat('v', 64)])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_browser_completion_cannot_approve_a_different_user(): void
    {
        $code = $this->begin();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->withSession(['native.code' => $code, 'native.user_id' => $other->id])->get('/auth/mobile/complete')->assertForbidden();
        $this->assertNull(Cache::get('native-auth:'.hash('sha256', $code))['user_id']);
    }

    public function test_cancelled_native_provider_flow_returns_an_error_without_a_bearer_token(): void
    {
        $code = $this->begin();
        $this->withSession(['native.code' => $code, 'social.intent' => ['provider' => 'google', 'type' => 'login', 'user_id' => null]])
            ->get('/auth/google/callback?error=access_denied')->assertRedirect('motominator://auth-return?error=social');
        $this->assertDatabaseCount('personal_access_tokens', 0);

    }
}
