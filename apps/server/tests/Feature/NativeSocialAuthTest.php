<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
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
        $response = $this->postJson(
            '/api/v1/auth/native',
            ['provider' => 'google', 'device_name' => 'Android', 'challenge' => hash('sha256', str_repeat('v', 64))]
        )->assertCreated();

        return $this->stringValue($response->json('code'));
    }

    public function testNativeHandoffRequiresBrowserAuthorizationAndAMatchingVerifier(): void
    {
        $code = $this->begin();
        $this->postJson(
            '/api/v1/auth/native/exchange',
            ['code' => $code, 'verifier' => str_repeat('v', 64)]
        )->assertUnprocessable();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(
            [
                'native.code' => $code, 'native.user_id' => $user->id,
                'native.security_fingerprint' => $user->securityFingerprint(includeRecoveryCodes: false),
            ]
        )
            ->get('/auth/mobile/complete')->assertRedirect('motominator://auth-return?code=' . $code);
        $this->assertGuest('web');
        $this->assertFalse(session()->has('native.code'));
        Auth::forgetGuards();
        $this->postJson(
            '/api/v1/auth/native/exchange',
            ['code' => $code, 'verifier' => str_repeat('x', 64)]
        )->assertUnprocessable();
        $this->postJson(
            '/api/v1/auth/native/exchange',
            ['code' => $code, 'verifier' => str_repeat('v', 64)]
        )->assertCreated()->assertJsonStructure(
            ['token', 'expires_at']
        );
        $this->postJson(
            '/api/v1/auth/native/exchange',
            ['code' => $code, 'verifier' => str_repeat('v', 64)]
        )->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function testExpiredHandoffCannotIssueAToken(): void
    {
        $this->freezeTime();
        $code = $this->begin();
        $this->travel(6)->minutes();
        $this->postJson(
            '/api/v1/auth/native/exchange',
            ['code' => $code, 'verifier' => str_repeat('v', 64)]
        )->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testBrowserCanStartAnotherNativeLoginAfterCompletion(): void
    {
        $code = $this->begin();
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([
            'native.code' => $code,
            'native.user_id' => $user->id,
            'native.security_fingerprint' => $user->securityFingerprint(includeRecoveryCodes: false),
            'auth.password_confirmed_at' => now()->timestamp,
        ])->get('/auth/mobile/complete')->assertRedirect();

        $this->assertGuest('web');
        $this->assertFalse(session()->has('auth.password_confirmed_at'));
        Socialite::fake('google', ProviderUser::fake());
        $next = $this->begin();
        $this->get('/auth/google/redirect?native=' . $next)->assertRedirect();
        $this->assertSame($next, session()->get('native.code'));
    }

    public function testBrowserCompletionCannotApproveADifferentUser(): void
    {
        $code = $this->begin();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->withSession(['native.code' => $code, 'native.user_id' => $other->id])->get(
            '/auth/mobile/complete'
        )->assertForbidden();
        $this->assertNull(data_get(Cache::get('native-auth:' . hash('sha256', $code)), 'user_id'));
    }

    public function testBrowserCompletionRejectsAnExpiredIntent(): void
    {
        $this->freezeTime();
        $code = $this->begin();
        $user = User::factory()->create();
        $this->travel(6)->minutes();

        $this->actingAs($user)->withSession(
            [
                'native.code' => $code, 'native.user_id' => $user->id,
                'native.security_fingerprint' => $user->securityFingerprint(includeRecoveryCodes: false),
            ]
        )
            ->getJson('/auth/mobile/complete')->assertGone()->assertJsonPath('reason', 'expired_intent');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testCancelledNativeProviderFlowReturnsAnErrorWithoutABearerToken(): void
    {
        $code = $this->begin();
        $this->withSession(
            ['native.code' => $code, 'social.intent' => ['provider' => 'google', 'type' => 'login', 'user_id' => null]]
        )
            ->get('/auth/google/callback?error=access_denied')->assertRedirect(
                'motominator://auth-return?error=social'
            );
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
