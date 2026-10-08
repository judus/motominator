<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Tests\TestCase;

class SocialAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google' => ['client_id' => 'test', 'client_secret' => 'test', 'redirect' => 'http://localhost/auth/google/callback']]);
    }

    private function fakeProvider(string $id = 'google-123', ?string $email = 'rider@example.test', bool $verified = true): void
    {
        Socialite::fake('google', ProviderUser::fake(['id' => $id, 'name' => 'Rider', 'email' => $email])->setRaw(['email_verified' => $verified]));
    }

    private function intent(string $type = 'login', ?int $userId = null): array
    {
        return ['social.intent' => ['provider' => 'google', 'type' => $type, 'user_id' => $userId]];
    }

    public function test_only_configured_built_in_providers_are_available(): void
    {
        $this->get('/auth/apple/redirect')->assertNotFound();
        $this->get('/auth/github/redirect')->assertStatus(503);
        $this->fakeProvider();
        $this->get('/auth/google/redirect')->assertRedirect();
    }

    public function test_callback_requires_both_intent_and_valid_oauth_state(): void
    {
        $this->get('/auth/google/callback?code=test&state=wrong')->assertRedirect(config('fortify.frontend_url').'/login?error=social');
        $this->withSession($this->intent() + ['state' => 'expected'])
            ->get('/auth/google/callback?code=test&state=wrong')->assertRedirect(config('fortify.frontend_url').'/login?error=social');
        $this->assertGuest();
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_signup_creates_a_regular_verified_user_without_storing_provider_tokens(): void
    {
        $this->fakeProvider();
        $this->withSession($this->intent())->get('/auth/google/callback')->assertRedirect(config('fortify.frontend_url').'/account');
        $user = User::query()->sole();
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->password);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('social_identities', ['provider' => 'google', 'provider_user_id' => 'google-123', 'user_id' => $user->id]);
    }

    public function test_closed_registration_blocks_new_social_accounts(): void
    {
        config(['auth.registration_enabled' => false]);
        $this->fakeProvider();
        $this->withSession($this->intent())->get('/auth/google/callback')->assertRedirect(config('fortify.frontend_url').'/login?error=social');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_matches_are_never_silently_merged(): void
    {
        $user = User::factory()->create(['email' => 'rider@example.test']);
        $this->fakeProvider();
        $this->withSession($this->intent())->get('/auth/google/callback');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_missing_or_unverified_email_cannot_create_an_account(): void
    {
        $this->fakeProvider(email: null);
        $this->withSession($this->intent())->get('/auth/google/callback');
        $this->fakeProvider(verified: false);
        $this->withSession($this->intent())->get('/auth/google/callback');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_linking_requires_authentication_and_recent_confirmation(): void
    {
        $this->get('/auth/google/redirect?intent=link')->assertForbidden();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/auth/google/redirect?intent=link')->assertForbidden();
        $this->fakeProvider();
        $this->withSession($this->intent('link', $user->id) + ['auth.password_confirmed_at' => time()])->get('/auth/google/callback')->assertRedirect(config('fortify.frontend_url').'/account');
        $this->assertDatabaseHas('social_identities', ['user_id' => $user->id, 'provider' => 'google']);
    }

    public function test_a_provider_identity_cannot_be_linked_to_two_users(): void
    {
        $owner = User::factory()->create();
        $owner->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
        $other = User::factory()->create();
        $this->fakeProvider();
        $this->actingAs($other)->withSession($this->intent('link', $other->id) + ['auth.password_confirmed_at' => time()])->get('/auth/google/callback');
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertDatabaseHas('social_identities', ['user_id' => $owner->id]);
    }

    public function test_social_login_still_requires_existing_two_factor_authentication(): void
    {
        $user = User::factory()->create(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()]);
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
        $this->fakeProvider();
        $this->withSession($this->intent())->get('/auth/google/callback')->assertRedirect(config('fortify.frontend_url').'/login?two_factor=1');
        $this->assertGuest();
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_unlinking_cannot_remove_the_final_sign_in_method(): void
    {
        $user = User::factory()->create(['password' => null]);
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->deleteJson('/auth/google')->assertConflict();
        $this->assertDatabaseCount('social_identities', 1);
        $user->forceFill(['password' => 'new-password-123'])->save();
        $this->deleteJson('/auth/google')->assertOk();
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function test_cancelled_provider_authentication_does_not_create_or_log_in_a_user(): void
    {
        $this->withSession($this->intent())->get('/auth/google/callback?error=access_denied')->assertRedirect(config('fortify.frontend_url').'/login?error=social');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

    }
}
