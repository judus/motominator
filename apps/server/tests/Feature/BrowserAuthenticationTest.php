<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BrowserAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_cannot_load_the_current_user(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_login_loads_only_the_current_user_and_logout_ends_the_session(): void
    {
        $user = User::factory()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->getJson('/api/v1/user')->assertOk()->assertJsonPath('id', $user->id)
            ->assertJsonMissingPath('password')->assertJsonMissingPath('two_factor_secret');
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected_and_throttled(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_browser_origins_are_explicit_and_support_credentials(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST'])
            ->options('/login')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_untrusted_origins_are_not_allowed(): void
    {
        $this->withHeaders(['Origin' => 'https://untrusted.example'])
            ->getJson('/api/v1/auth/config')->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_login_requires_csrf_outside_the_test_environment(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/login', ['email' => 'rider@example.test', 'password' => 'password'])->assertStatus(419);
    }

    public function test_recovery_has_the_same_response_for_existing_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $expected = ['message' => 'If an account exists, a password reset link has been sent.'];
        $this->postJson('/forgot-password', ['email' => $user->email])->assertOk()->assertExactJson($expected);
        $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertOk()->assertExactJson($expected);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, config('fortify.frontend_url').'/reset-password?');
        });
    }

    public function test_recovery_endpoint_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertOk();
        }
        $this->postJson('/forgot-password', ['email' => 'missing@example.test'])->assertTooManyRequests();
    }

    public function test_reset_updates_password_and_rejects_reused_or_expired_tokens(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $user->createToken('phone', ['account:read']);
        $token = Password::broker('users')->createToken($user);
        $payload = ['email' => $user->email, 'token' => $token, 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $this->postJson('/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('replacement-password', $user->refresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/reset-password', $payload)->assertUnprocessable();
        $payload['token'] = Password::broker('users')->createToken($user);
        $this->travel(61)->minutes();
        $this->postJson('/reset-password', $payload)->assertUnprocessable();
    }

    public function test_invalid_reset_links_do_not_reveal_whether_an_account_exists(): void
    {
        $user = User::factory()->create();
        $payload = ['token' => 'invalid', 'password' => 'replacement-password', 'password_confirmation' => 'replacement-password'];
        $known = $this->postJson('/reset-password', $payload + ['email' => $user->email])->assertUnprocessable()->json();
        $unknown = $this->postJson('/reset-password', $payload + ['email' => 'missing@example.test'])->assertUnprocessable()->json();
        $this->assertSame($known, $unknown);
    }

    public function test_verification_mail_targets_the_browser_and_signed_api_link_verifies_only_its_user(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->postJson('/email/verification-notification')->assertStatus(202);
        Notification::assertSentTo($user, VerifyEmail::class, fn (VerifyEmail $notification): bool => str_starts_with($notification->toMail($user)->actionUrl, config('fortify.frontend_url').'/verify-email?'));
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->getJson($url)->assertNoContent();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_rejects_invalid_expired_and_foreign_links(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($user);
        $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), $parameters);
        $this->getJson($url.'tampered')->assertForbidden();
        $foreign = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => sha1($other->email)]);
        $this->getJson($foreign)->assertForbidden();
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);
        $this->getJson($expired)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_verification_resend_is_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/email/verification-notification')->assertStatus(202);
        }
        $this->postJson('/email/verification-notification')->assertTooManyRequests();
        Notification::assertSentToTimes($user, VerifyEmail::class, 6);
    }

    public function test_auth_config_reports_closed_registration_without_exposing_credentials(): void
    {
        config(['auth.registration_enabled' => false]);
        $this->getJson('/api/v1/auth/config')->assertOk()->assertExactJson(['registration_enabled' => false, 'providers' => []]);

    }
}
