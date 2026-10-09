<?php

namespace Tests\Feature;

use App\Accounts\Actions\CompleteNativeSocialLink;
use App\Accounts\Actions\ConsumeNativeSocialLink;
use App\Accounts\Actions\ManageAccountTwoFactor;
use App\Accounts\Actions\StartNativeSocialLink;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery;
use Tests\TestCase;

class NativeAccountSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @param list<string> $abilities */
    private function signIn(User $user, array $abilities = ['account:read', 'account:write']): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('Test phone', $abilities)->plainTextToken);
    }

    public function testAccountWritesRequireAuthenticationAndTheWriteAbility(): void
    {
        $this->putJson('/api/v1/account/profile', [])->assertUnauthorized();
        $user = User::factory()->create();
        $this->signIn($user, ['account:read']);
        $this->getJson('/api/v1/account/devices')->assertOk();
        $this->putJson('/api/v1/account/profile', ['name' => 'Changed', 'email' => $user->email])->assertForbidden();
        $this->assertNotSame('Changed', $user->refresh()->name);
    }

    public function testProfileUpdatesAreActorScopedAndResetVerificationWhenEmailChanges(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $this->signIn($user);
        $this->putJson('/api/v1/account/profile', [
            'name' => 'Mobile Rider', 'email' => 'mobile-rider@example.test',
            'id' => $foreign->id, 'current_password' => 'password',
        ])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Mobile Rider', 'email_verified_at' => null]);
        $this->assertDatabaseHas(
            'users',
            ['id' => $foreign->id, 'email' => $foreign->email]
        );
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->putJson('/api/v1/account/profile', ['name' => '', 'email' => $foreign->email])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    }

    public function testPasswordChangesCheckTheActorAndRevokeEveryDeviceToken(): void
    {
        $user = User::factory()->create();
        $user->createToken('Other phone', ['account:read']);
        $broker = $this->app->make(PasswordBroker::class);
        $resetToken = $broker->createToken($user);
        $this->signIn($user);
        $input = ['current_password' => 'wrong', 'password' => 'replacement-password',
            'password_confirmation' => 'replacement-password'];
        $this->putJson('/api/v1/account/password', $input)->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->assertSame(2, $user->tokens()->count());
        $input['current_password'] = 'password';
        $this->putJson('/api/v1/account/password', $input)->assertOk();
        $this->assertTrue(Hash::check('replacement-password', $user->refresh()->password ?? ''));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertFalse($broker->tokenExists($user->refresh(), $resetToken));
        Auth::forgetGuards();
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function testTwoFactorSetupRequiresPasswordAndAValidAuthenticatorCode(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        $this->postJson('/api/v1/account/two-factor', ['operation' => 'enable', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $setup = $this->postJson('/api/v1/account/two-factor', ['operation' => 'enable', 'password' => 'password'])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(8, 'codes');
        $secret = $this->stringValue($setup->json('secret'));
        $this->assertNotSame($secret, $user->refresh()->two_factor_secret);
        $this->assertNull($user->refresh()->two_factor_confirmed_at);
        $this->mock(TwoFactorAuthenticationProvider::class, function (MockInterface $mock) use ($secret): void {
            $mock->shouldReceive('verify')->with($secret, '123456')->once()->andReturn(true);
            $mock->shouldReceive('verify')->with($secret, '000000')->once()->andReturn(false);
        });
        $this->postJson('/api/v1/account/two-factor', [
            'operation' => 'confirm', 'password' => 'password', 'code' => '000000',
        ])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson('/api/v1/account/two-factor', [
            'operation' => 'confirm', 'password' => 'password', 'code' => '123456',
        ])->assertOk()->assertJsonPath('secret', null);
        $this->assertTrue($user->refresh()->hasEnabledTwoFactorAuthentication());
        $oldCodes = $user->refresh()->two_factor_recovery_codes;
        $this->postJson('/api/v1/account/two-factor', ['operation' => 'regenerate', 'password' => 'password'])
            ->assertOk()->assertJsonCount(8, 'codes');
        $this->assertNotSame($oldCodes, $user->refresh()->two_factor_recovery_codes);
        $this->postJson('/api/v1/account/two-factor', ['operation' => 'disable', 'password' => 'password'])->assertOk();
        $this->assertNull($user->refresh()->two_factor_secret);
    }

    public function testTwoFactorActionRejectsWrongPasswordsWithoutAnHttpGuard(): void
    {
        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        $this->app->make(ManageAccountTwoFactor::class)($user, ['operation' => 'enable', 'password' => 'wrong']);
    }

    public function testDeviceManagementDoesNotExposeOrRevokeAnotherUsersToken(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create()->createToken('Foreign phone');
        $this->signIn($user);
        $this->getJson('/api/v1/account/devices')->assertOk()->assertJsonCount(1)
            ->assertJsonMissingPath('0.token')->assertJsonPath('0.current_device', true);
        $this->deleteJson('/api/v1/account/devices/' . $foreign->accessToken->id, ['password' => 'password'])
            ->assertNotFound();
        $current = $user->tokens()->sole();
        $this->deleteJson('/api/v1/account/devices/' . $current->id, ['password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/account/devices/' . $current->id, ['password' => 'password'])
            ->assertOk()->assertJsonPath('current_device', true);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }

    public function testUnlinkingAndVerificationWorkWithDeviceTokens(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-user']);
        $this->signIn($user);
        $this->deleteJson('/api/v1/account/social/google', ['password' => 'wrong'])->assertUnprocessable();
        $this->deleteJson('/api/v1/account/social/google', ['password' => 'password'])->assertOk();
        $this->assertSame(0, $user->socialIdentities()->count());
        $this->postJson('/api/v1/account/verification')->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function testNativeLinkRequiresProofAndIsSingleUseWithoutIssuingAnotherToken(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $verifier = str_repeat('a', 64);
        $code = $this->app->make(StartNativeSocialLink::class)($actor, [
            'provider' => 'google', 'password' => 'password', 'challenge' => hash('sha256', $verifier),
        ]);
        $this->app->make(CompleteNativeSocialLink::class)($code, 'google', 'provider-id');
        $this->assertDatabaseCount('social_identities', 0);
        $consume = $this->app->make(ConsumeNativeSocialLink::class);
        foreach ([[$other, $verifier], [$actor, str_repeat('b', 64)]] as [$user, $proof]) {
            try {
                $consume($user, ['code' => $code, 'verifier' => $proof]);
                $this->fail('Foreign actors and invalid proof must be rejected.');
            } catch (AccountsNativeAuthException $exception) {
                $this->assertSame('invalid_exchange', $exception->reason);
                $this->assertDatabaseCount('social_identities', 0);
            }
        }
        $consume($actor, ['code' => $code, 'verifier' => $verifier]);
        $this->assertDatabaseHas('social_identities', ['user_id' => $actor->id, 'provider_user_id' => 'provider-id']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->expectException(AccountsNativeAuthException::class);
        $consume($actor, ['code' => $code, 'verifier' => $verifier]);
    }

    public function testNativeLinkBrowserCallbackUsesTheIntentActorAndRetainsOauthState(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $actor = User::factory()->create();
        $this->signIn($actor);
        $response = $this->postJson('/api/v1/account/social/link', [
            'provider' => 'google', 'password' => 'password', 'challenge' => hash('sha256', str_repeat('a', 64)),
        ])->assertCreated();
        $code = $this->stringValue($response->json('code'));
        $this->assertStringNotContainsString('Bearer', $this->stringValue($response->json('url')));
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('redirect')->once()->andReturn(redirect('https://example.test/oauth'));
        $driver->shouldReceive('user')->once()->andReturn((new ProviderUser())->map(['id' => 'linked-id']));
        Socialite::shouldReceive('driver')->with('google')->twice()->andReturn($driver);
        // A native request and its browser handoff have independent guard state.
        Auth::forgetGuards();
        Auth::shouldUse('web');
        $this->withHeaders(['Authorization' => '']);
        $this->get('/auth/google/redirect?native_link=' . $code)->assertRedirect('https://example.test/oauth');
        $this->get('/auth/google/callback')->assertRedirect(
            Config::string('auth.mobile_return_url') . '?link_code=' . $code
        );
        $this->assertDatabaseCount('social_identities', 0);
        $this->signIn($actor);
        $this->postJson('/api/v1/account/social/link/complete', [
            'code' => $code, 'verifier' => str_repeat('a', 64),
        ])->assertOk();
        $this->assertDatabaseHas('social_identities', ['user_id' => $actor->id, 'provider_user_id' => 'linked-id']);
    }
}
