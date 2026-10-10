<?php

namespace Tests\Feature;

use App\Accounts\Actions\ApproveNativeAuth;
use App\Accounts\Actions\CompleteNativeSocialLink;
use App\Accounts\Actions\ConsumeNativeSocialLink;
use App\Accounts\Actions\ExchangeNativeAuth;
use App\Accounts\Actions\Fortify\UpdateUserPassword;
use App\Accounts\Actions\Fortify\ResetUserPassword;
use App\Accounts\Actions\LinkSocialIdentity;
use App\Accounts\Actions\UnlinkSocialIdentity;
use App\Accounts\Actions\RevokeDeviceToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use App\Accounts\Actions\StartNativeAuth;
use App\Accounts\Actions\StartNativeSocialLink;
use App\Accounts\Exceptions\AccountsNativeAuthException;
use App\Accounts\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Fortify;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AccountSecurityRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['/user/profile-information', false])]
    #[TestWith(['/api/v1/account/profile', true])]
    public function testEmailChangesRequirePasswordButNameChangesDoNot(string $path, bool $native): void
    {
        Notification::fake();
        $user = User::factory()->create();
        if ($native) {
            $this->withToken($user->createToken('Phone', ['account:write'])->plainTextToken);
        } else {
            $this->actingAs($user);
        }
        $this->putJson($path, ['name' => 'New name', 'email' => $user->email])->assertOk();
        foreach (['', 'incorrect'] as $password) {
            $this->putJson($path, [
                'name' => 'Attempted change', 'email' => 'attacker@example.test', 'current_password' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
            $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New name', 'email' => $user->email]);
        }
        Notification::assertNothingSent();
    }

    #[TestWith(['password'])]
    #[TestWith(['email'])]
    #[TestWith(['two_factor_secret'])]
    #[TestWith(['two_factor_recovery_codes'])]
    public function testApprovedNativeLoginRejectsSecurityChanges(string $field): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $user = User::factory()->create();
        $proof = str_repeat('v', 64);
        $code = $this->app->make(StartNativeAuth::class)([
            'provider' => 'google', 'device_name' => 'Phone', 'challenge' => hash('sha256', $proof),
        ]);
        $this->app->make(ApproveNativeAuth::class)($user, $user->id, $code);
        $value = match ($field) {
            'password' => 'new-password',
            'email' => 'changed@example.test',
            default => Fortify::currentEncrypter()->encrypt('changed-security-value'),
        };
        $user->forceFill([$field => $value])->save();
        try {
            $this->app->make(ExchangeNativeAuth::class)(['code' => $code, 'verifier' => $proof]);
            $this->fail('Revoked approval must not issue a token.');
        } catch (AccountsNativeAuthException $exception) {
            $this->assertSame('invalid_exchange', $exception->reason);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testPasswordResetRevokesAnAlreadyApprovedNativeLogin(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $user = User::factory()->create();
        $proof = str_repeat('v', 64);
        $code = $this->app->make(StartNativeAuth::class)([
            'provider' => 'google', 'device_name' => 'Phone', 'challenge' => hash('sha256', $proof),
        ]);
        $this->app->make(ApproveNativeAuth::class)($user, $user->id, $code);
        $reset = $this->app->make(PasswordBroker::class)->createToken($user);
        $this->postJson('/reset-password', [
            'email' => $user->email, 'token' => $reset,
            'password' => 'reset-password', 'password_confirmation' => 'reset-password',
        ])->assertOk();
        $this->postJson('/api/v1/auth/native/exchange', ['code' => $code, 'verifier' => $proof])
            ->assertUnprocessable()->assertJsonPath('reason', 'invalid_exchange');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testNativeApprovalRejectsChangesAfterBrowserAuthentication(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $user = User::factory()->create();
        $fingerprint = $user->securityFingerprint(includeRecoveryCodes: false);
        $code = $this->app->make(StartNativeAuth::class)([
            'provider' => 'google', 'device_name' => 'Phone', 'challenge' => hash('sha256', str_repeat('v', 64)),
        ]);
        $user->forceFill(['password' => 'changed-password'])->save();
        $this->expectException(AccountsNativeAuthException::class);
        $this->app->make(ApproveNativeAuth::class)($user, $user->id, $code, $fingerprint);
    }

    public function testPendingNativeLinkRejectsSecurityChanges(): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $user = User::factory()->create();
        $proof = str_repeat('v', 64);
        $code = $this->app->make(StartNativeSocialLink::class)($user, [
            'provider' => 'google', 'password' => 'password', 'challenge' => hash('sha256', $proof),
        ]);
        $this->app->make(CompleteNativeSocialLink::class)($code, 'google', 'pending-identity');
        $user->forceFill(['password' => 'new-password'])->save();
        try {
            $this->app->make(ConsumeNativeSocialLink::class)($user, ['code' => $code, 'verifier' => $proof]);
            $this->fail('Security change must invalidate a pending link.');
        } catch (AccountsNativeAuthException $exception) {
            $this->assertSame('invalid_exchange', $exception->reason);
        }
        $this->assertDatabaseCount('social_identities', 0);
    }

    public function testPasswordUpdateRejectsAStaleActorAfterAnAdministrativeReset(): void
    {
        $actor = User::factory()->create();
        User::query()->findOrFail($actor->id)->forceFill(['password' => 'administrator-password'])->save();
        try {
            $this->app->make(UpdateUserPassword::class)->update($actor, [
                'current_password' => 'password', 'password' => 'attacker-password',
                'password_confirmation' => 'attacker-password',
            ]);
            $this->fail('The current password must be checked against the locked current user.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('current_password', $exception->errors());
        }
        $this->assertTrue(Hash::check('administrator-password', $actor->refresh()->password ?? ''));
    }

    public function testResetRechecksTokenRevocationAfterTheBrokerLoadedItsActor(): void
    {
        $actor = User::factory()->create();
        $broker = $this->app->make(PasswordBroker::class);
        $token = $broker->createToken($actor);
        $this->assertTrue($broker->tokenExists($actor, $token));
        User::query()->findOrFail($actor->id)->forceFill(['password' => 'administrator-password'])->save();
        try {
            $this->app->make(ResetUserPassword::class)->reset($actor, [
                'token' => $token, 'password' => 'attacker-password', 'password_confirmation' => 'attacker-password',
            ]);
            $this->fail('A previously validated token must still be valid at the locked write.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        $this->assertTrue(Hash::check('administrator-password', $actor->refresh()->password ?? ''));
    }

    #[TestWith(['link'])]
    #[TestWith(['unlink'])]
    #[TestWith(['revoke'])]
    public function testConfirmedSecurityWritesRejectStaleCredentials(string $operation): void
    {
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
        $actor = User::factory()->create();
        $actor->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'original-id']);
        $fresh = User::query()->findOrFail($actor->id);
        $fresh->forceFill(['password' => 'administrator-password'])->save();
        $device = $fresh->createToken('Current phone', ['account:read']);
        try {
            match ($operation) {
                'link' => $this->app->make(LinkSocialIdentity::class)(
                    $actor,
                    'google', 'replacement-id', now()->getTimestamp()
                ),
                'unlink' => $this->app->make(UnlinkSocialIdentity::class)($actor, 'google', now()->getTimestamp()),
                default => $this->app->make(
                    RevokeDeviceToken::class
                )($actor, $device->accessToken->id, now()->getTimestamp()),
            };
            $this->fail('Credential changes invalidate earlier confirmations.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas(
                'social_identities',
                ['user_id' => $actor->id, 'provider_user_id' => 'original-id']
            );
            $this->assertDatabaseHas('personal_access_tokens', ['id' => $device->accessToken->id]);
        }
    }

    public function testAdminPasswordChangeRevokesEarlierResetTokensAndDeviceAccess(): void
    {
        $user = User::factory()->create();
        $broker = $this->app->make(PasswordBroker::class);
        $reset = $broker->createToken($user);
        $user->createToken('Old device', ['account:read']);
        $editor = new class extends EditUser {
            /** @param array<string, mixed> $data */
            public function updateUser(User $user, array $data): Model
            {
                return $this->handleRecordUpdate($user, $data);
            }
        };
        $editor->updateUser($user, ['email' => $user->email, 'password' => Hash::make('admin-password')]);
        $this->assertTrue(Hash::check('admin-password', $user->refresh()->password ?? ''));
        $this->assertFalse($broker->tokenExists($user, $reset));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testAdminPasswordAndRevocationRollBackTogetherWhenBrokerFails(): void
    {
        $user = User::factory()->create();
        $user->createToken('Old device', ['account:read']);
        $broker = Mockery::mock(PasswordBroker::class);
        $broker->shouldReceive('deleteToken')->once()->andThrow(new RuntimeException('Broker unavailable.'));
        $this->instance(PasswordBroker::class, $broker);
        $editor = new class extends EditUser {
            /** @param array<string, mixed> $data */
            public function updateUser(User $user, array $data): Model
            {
                return $this->handleRecordUpdate($user, $data);
            }
        };
        try {
            $editor->updateUser($user, ['email' => $user->email, 'password' => Hash::make('admin-password')]);
            $this->fail('A failed reset-token revocation must roll back the password change.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Broker unavailable.', $exception->getMessage());
        }
        $this->assertTrue(Hash::check('password', $user->refresh()->password ?? ''));
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function testLegacyPasswordChecksShareActorLimitAcrossPathsAndIps(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.' . ($attempt + 1)])
                ->postJson('/user/confirm-password', ['password' => 'incorrect'])->assertUnprocessable();
        }
        $this->putJson('/user/password', [
            'current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertTooManyRequests();
        $this->assertTrue(Hash::check('password', $user->refresh()->password ?? ''));
    }
}
