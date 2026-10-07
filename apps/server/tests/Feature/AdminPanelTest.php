<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guests_are_redirected_to_admin_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function test_regular_users_cannot_access_user_management_even_locally(): void
    {
        config(['app.env' => 'local']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_administrators_can_access_user_management(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function test_registration_creates_a_regular_user_and_returns_to_guest_state(): void
    {
        Notification::fake();

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'New Rider',
                'email' => 'rider@example.test',
                'password' => 'safe-password-123',
                'passwordConfirmation' => 'safe-password-123',
            ])
            ->set('data.is_admin', true)
            ->call('register')
            ->assertHasNoFormErrors()
            ->assertNotified('Account created');

        $user = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('safe-password-123', $user->password));
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_disabled_registration_rejects_an_already_mounted_form(): void
    {
        $component = Livewire::test(Register::class);
        config(['auth.registration_enabled' => false]);

        $component->call('register')->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_disabled_registration_rejects_the_registration_page(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->get('/admin/register')->assertForbidden();
    }

    public function test_signed_out_verification_links_redirect_to_the_available_login_page(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('/admin/login');
    }

    public function test_registration_validates_required_fields(): void
    {
        Livewire::test(Register::class)
            ->fillForm(['name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => ''])
            ->call('register')
            ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'password' => 'required']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_fortify_registration_cannot_grant_administrator_access(): void
    {
        Notification::fake();

        $this->postJson('/register', [
            'name' => 'New Rider',
            'email' => 'rider@example.test',
            'password' => 'safe-password-123',
            'password_confirmation' => 'safe-password-123',
            'is_admin' => true,
        ])->assertCreated();

        $user = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_administrators_can_create_regular_users_with_hashed_passwords(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'New Rider', 'email' => 'rider@example.test', 'password' => 'safe-password-123'])
            ->set('data.is_admin', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('safe-password-123', $user->password));
    }

    public function test_editing_email_clears_verification_and_blank_password_preserves_password(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => 'Updated Rider', 'email' => 'updated@example.test', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertSame('Updated Rider', $user->name);
        $this->assertSame('updated@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_administrators_can_sign_in(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_regular_users_cannot_sign_in_to_the_admin_panel(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_confirmed_fortify_two_factor_authentication_requires_an_admin_login_challenge(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertSet('userUndertakingMultiFactorAuthentication', fn (?string $value): bool => filled($value));

        $this->assertGuest();
    }

    public function test_console_command_grants_administrator_access_to_an_existing_user(): void
    {
        $user = User::factory()->create();

        $this->artisan('app:grant-admin', ['email' => $user->email])->assertSuccessful();

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function test_confirmed_fortify_authenticator_code_completes_admin_login(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ]);
        $login = Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate');

        $login->set('data.multiFactor.app.code', (new Google2FA)->getCurrentOtp($secret))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function test_password_edits_are_hashed_and_cannot_change_administrator_status(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => $user->name, 'email' => $user->email, 'password' => 'replacement-password'])
            ->set('data.is_admin', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('replacement-password', $user->password));
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_console_command_does_not_create_an_unknown_account(): void
    {
        $this->artisan('app:grant-admin', ['email' => 'missing@example.test'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
