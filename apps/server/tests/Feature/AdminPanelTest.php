<?php

namespace Tests\Feature;

use App\Accounts\Filament\Auth\Register;
use App\Accounts\Filament\Resources\Users\Pages\CreateUser;
use App\Accounts\Filament\Resources\Users\Pages\EditUser;
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

    public function testGuestsAreRedirectedToAdminLogin(): void
    {
        $this->get('/admin/users')->assertRedirect('/admin/login');
    }

    public function testRegularUsersCannotAccessUserManagementEvenLocally(): void
    {
        config(['app.env' => 'local']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function testAdministratorsCanAccessUserManagement(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/users')->assertOk();
    }

    public function testRegistrationCreatesARegularUserAndReturnsToGuestState(): void
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
        $this->assertTrue(Hash::check('safe-password-123', $user->password ?? ''));
        $this->assertGuest();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function testDisabledRegistrationRejectsAnAlreadyMountedForm(): void
    {
        $component = Livewire::test(Register::class);
        config(['auth.registration_enabled' => false]);

        $component->call('register')->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function testDisabledRegistrationRejectsTheRegistrationPage(): void
    {
        config(['auth.registration_enabled' => false]);

        $this->get('/admin/register')->assertForbidden();
    }

    public function testSignedOutVerificationLinksRedirectToTheAvailableLoginPage(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('/admin/login');
    }

    public function testRegistrationValidatesRequiredFields(): void
    {
        Livewire::test(Register::class)
            ->fillForm(['name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => ''])
            ->call('register')
            ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'password' => 'required']);

        $this->assertDatabaseCount('users', 0);
    }

    public function testFortifyRegistrationCannotGrantAdministratorAccess(): void
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

    public function testAdministratorsCanCreateRegularUsersWithHashedPasswords(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'New Rider', 'email' => 'rider@example.test', 'password' => 'safe-password-123'])
            ->set('data.is_admin', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'rider@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('safe-password-123', $user->password ?? ''));
    }

    public function testEditingEmailClearsVerificationAndBlankPasswordPreservesPassword(): void
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
        $this->assertTrue(Hash::check('password', $user->password ?? ''));
    }

    public function testAdministratorsCanSignIn(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function testRegularUsersCannotSignInToTheAdminPanel(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function testConfirmedFortifyTwoFactorAuthenticationRequiresAnAdminLoginChallenge(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                'JBSWY3DPEHPK3PXP'
            ),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertSet('userUndertakingMultiFactorAuthentication', fn (?string $value): bool => filled($value));

        $this->assertGuest();
    }

    public function testConsoleCommandGrantsAdministratorAccessToAnExistingUser(): void
    {
        $user = User::factory()->create();

        $this->pendingCommand('app:grant-admin', ['email' => $user->email])->assertSuccessful();

        $this->assertTrue($user->refresh()->is_admin);
    }

    public function testConfirmedFortifyAuthenticatorCodeCompletesAdminLogin(): void
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

        $login->set('data.multiFactor.app.code', (new Google2FA())->getCurrentOtp($secret))
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
    }

    public function testPasswordEditsAreHashedAndCannotChangeAdministratorStatus(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['name' => $user->name, 'email' => $user->email, 'password' => 'replacement-password'])
            ->set('data.is_admin', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('replacement-password', $user->password ?? ''));
        $this->assertFalse($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
    }

    public function testConsoleCommandDoesNotCreateAnUnknownAccount(): void
    {
        $this->pendingCommand('app:grant-admin', ['email' => 'missing@example.test'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
