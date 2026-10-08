<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_profile_updates_only_the_authenticated_account_and_clears_verification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->putJson('/user/profile-information', ['name' => 'Updated Rider', 'email' => 'new@example.test', 'id' => $other->id, 'is_admin' => true])->assertOk();
        $this->assertSame('Updated Rider', $user->fresh()->name);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertFalse($user->fresh()->is_admin);
        $this->assertSame($other->name, $other->fresh()->name);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_password_updates_require_the_current_password(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone', ['account:read']);
        $this->actingAs($user)->putJson('/user/password', ['current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertUnprocessable();
        $this->putJson('/user/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertOk();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_a_browser_session_with_an_old_password_hash_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['password_hash_web' => 'old-password-hash'])
            ->getJson('/user/devices')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_two_factor_secrets_and_changes_require_recent_password_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/user/two-factor-authentication')->assertStatus(423);
        $this->getJson('/user/two-factor-recovery-codes')->assertStatus(423);
        $this->getJson('/user/two-factor-secret-key')->assertStatus(423);
        $this->postJson('/user/confirm-password', ['password' => 'wrong'])->assertUnprocessable();
    }

    public function test_two_factor_enrollment_confirmation_and_disabling(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'password'])->assertSuccessful();
        $this->postJson('/user/two-factor-authentication')->assertSuccessful();
        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
        $secret = $this->getJson('/user/two-factor-secret-key')->assertOk()->json('secretKey');
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => 'invalid'])->assertUnprocessable();
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSuccessful();
        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->deleteJson('/user/two-factor-authentication')->assertSuccessful();
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_recovery_codes);
    }

    public function test_recovery_codes_are_rotated_and_consumed_by_login(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/user/confirm-password', ['password' => 'password']);
        $this->postJson('/user/two-factor-authentication');
        $secret = $this->getJson('/user/two-factor-secret-key')->json('secretKey');
        $this->postJson('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)]);
        $original = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->postJson('/user/two-factor-recovery-codes')->assertSuccessful();
        $codes = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->assertNotSame($original, $codes);
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertJsonPath('two_factor', true);
        $this->postJson('/two-factor-challenge', ['code' => 'invalid'])->assertUnprocessable();
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertNoContent();
        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($codes[0], $user->fresh()->recoveryCodes());
        $this->postJson('/logout');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password']);
        $this->postJson('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertUnprocessable();
        $this->assertGuest();

    }
}
