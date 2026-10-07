<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccountRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_login_links_to_the_password_reset_form(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee(Filament::getRequestPasswordResetUrl(), false);
        $this->get(Filament::getRequestPasswordResetUrl())->assertOk();
    }

    public function test_admin_reset_email_opens_the_panel_and_resets_the_shared_password(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $admin->email])
            ->call('request')
            ->assertHasNoFormErrors();

        $notification = Notification::sent($admin, ResetPasswordNotification::class)->sole();
        $this->assertStringContainsString('/admin/password-reset/reset?', $notification->url);
        $this->get($notification->url)->assertOk();

        Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $notification->token])
            ->fillForm(['password' => 'replacement-password-123', 'passwordConfirmation' => 'replacement-password-123'])
            ->call('resetPassword')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin/login');

        $this->assertTrue(Hash::check('replacement-password-123', $admin->refresh()->password));
        $this->assertFalse(Password::broker('users')->tokenExists($admin, $notification->token));
        $this->assertGuest();
    }

    public function test_regular_users_do_not_receive_admin_reset_links(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        Notification::assertNothingSent();
    }

    public function test_invalid_reset_tokens_do_not_change_passwords(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => 'invalid-token'])
            ->fillForm(['password' => 'replacement-password-123', 'passwordConfirmation' => 'replacement-password-123'])
            ->call('resetPassword')
            ->assertNotified(__('passwords.token'));

        $this->assertTrue(Hash::check('password', $admin->refresh()->password));
    }

    public function test_reset_links_reject_tampered_signatures(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $url = Filament::getResetPasswordUrl('token', $admin).'&extra=tampered';

        $this->get($url)->assertForbidden();
    }

    public function test_unverified_administrators_are_sent_to_verification_before_user_management(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertRedirect(Filament::getEmailVerificationPromptUrl());
        $this->get(Filament::getEmailVerificationPromptUrl())->assertOk();
    }

    public function test_resending_verification_sends_a_panel_link_that_unlocks_access(): void
    {
        Notification::fake();
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $this->actingAs($admin);

        Livewire::test(EmailVerificationPrompt::class)->callAction('resendNotification');

        $notification = Notification::sent($admin, VerifyEmail::class)->sole();
        $this->assertStringContainsString('/admin/email-verification/verify/', $notification->url);
        $this->get($notification->url)->assertRedirect('/admin');
        $this->assertNotNull($admin->refresh()->email_verified_at);
        $this->get('/admin/users')->assertOk();
    }

    public function test_verification_rejects_expired_links(): void
    {
        $this->freezeTime();
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $url = Filament::getVerifyEmailUrl($admin);
        $this->travel(61)->minutes();

        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->assertNull($admin->refresh()->email_verified_at);
    }

    public function test_verification_rejects_links_for_a_different_account(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);
        $other = User::factory()->unverified()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(Filament::getVerifyEmailUrl($other))->assertForbidden();

        $this->assertNull($admin->refresh()->email_verified_at);
        $this->assertNull($other->refresh()->email_verified_at);
    }

    public function test_signed_out_verification_links_require_admin_login(): void
    {
        $admin = User::factory()->unverified()->create(['is_admin' => true]);

        $this->get(Filament::getVerifyEmailUrl($admin))->assertRedirect('/admin/login');
        $this->assertNull($admin->refresh()->email_verified_at);
    }
}
