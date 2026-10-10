<?php

namespace Tests\Feature;

use App\Accounts\Actions\ApproveNativeAuth;
use App\Accounts\Actions\AuthenticateDevice;
use App\Accounts\Actions\AuthenticateSocialAccount;
use App\Accounts\Actions\ExchangeNativeAuth;
use App\Accounts\Actions\IssueDeviceToken;
use App\Accounts\Actions\LinkSocialIdentity;
use App\Accounts\Actions\RevokeCurrentDeviceToken;
use App\Accounts\Actions\RevokeDeviceToken;
use App\Accounts\Actions\UnlinkSocialIdentity;
use App\Accounts\Actions\StartNativeAuth;
use App\Accounts\Exceptions\AccountsSocialException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Laravel\Socialite\Two\User as ProviderUser;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class AccountsActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test', 'services.google.client_secret' => 'test']);
    }

    public function testDeviceAuthenticationValidatesInputWithoutAnHttpRequest(): void
    {
        try {
            $this->app->make(AuthenticateDevice::class)([]);
            $this->fail('Invalid device credentials must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['email', 'password', 'device_name'], array_keys($exception->errors()));
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testFailedTokenIssuanceRollsBackRecoveryCodeConsumption(): void
    {
        $user = User::factory()->create([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code'])),
        ]);
        $this->mock(IssueDeviceToken::class, function (MockInterface $mock): void {
            $mock->shouldReceive('__invoke')->once()->andThrow(new RuntimeException('Token persistence failed.'));
        });

        try {
            $this->app->make(AuthenticateDevice::class)([
                'email' => $user->email,
                'password' => 'password',
                'device_name' => 'Android',
                'recovery_code' => 'recovery-code',
            ]);
            $this->fail('A persistence failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Token persistence failed.', $exception->getMessage());
        }
        $this->assertSame(['recovery-code'], $user->refresh()->recoveryCodes());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testNativeHandoffReturnsApplicationDataWithoutARequestOrSession(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $code = $this->app->make(StartNativeAuth::class)([
            'provider' => 'google',
            'challenge' => hash('sha256', str_repeat('v', 64)),
            'device_name' => 'Direct client',
        ]);
        $this->app->make(ApproveNativeAuth::class)($user, $user->id, $code);
        $result = $this->app->make(ExchangeNativeAuth::class)(['code' => $code, 'verifier' => str_repeat('v', 64)]);

        $token = PersonalAccessToken::findToken($result['token']);
        $this->assertInstanceOf(PersonalAccessToken::class, $token);
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame('Direct client', $token->name);
        $this->assertSame(now()->addDays(30)->toIso8601String(), $result['expires_at']);
        $this->assertGuest();
    }

    public function testSocialAuthenticationResolvesIdentityWithoutLoggingIntoAGuard(): void
    {
        $user = User::factory()->create();
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
        config(['auth.registration_enabled' => false]);
        $external = ProviderUser::fake(['id' => 'google-123', 'email' => null]);

        $resolved = $this->app->make(AuthenticateSocialAccount::class)('google', $external);

        $this->assertNotNull($resolved);
        $this->assertTrue($resolved->is($user));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function testLinkingRejectsStaleConfirmationWithoutAnHttpMiddleware(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $confirmedAt = now()->getTimestamp() - Config::integer('auth.password_timeout');

        try {
            $this->app->make(LinkSocialIdentity::class)($user, 'google', 'google-123', $confirmedAt);
            $this->fail('Expired password confirmation must be rejected.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('social_identities', 0);
        }
    }

    public function testLinkingUsesTheExplicitActorAndRejectsAnIdentityOwnedByAnotherAccount(): void
    {
        $this->freezeTime();
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($other);
        $link = $this->app->make(LinkSocialIdentity::class);
        $link($actor, 'google', 'google-123', now()->getTimestamp());
        $this->assertDatabaseHas('social_identities', ['user_id' => $actor->id, 'provider_user_id' => 'google-123']);

        try {
            $link($other, 'google', 'google-123', now()->getTimestamp());
            $this->fail('An identity must not move between accounts.');
        } catch (AccountsSocialException $exception) {
            $this->assertSame('identity_already_linked', $exception->reason);
        }
        $this->assertDatabaseCount('social_identities', 1);
    }

    public function testIdentityConflictKeepsTheExistingLinkAndPreservesTheCause(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-original']);

        try {
            $this->app->make(LinkSocialIdentity::class)($user, 'google', 'google-other', now()->getTimestamp());
            $this->fail('An account cannot have two identities for one provider.');
        } catch (AccountsSocialException $exception) {
            $this->assertSame('identity_conflict', $exception->reason);
            $this->assertInstanceOf(
                \Illuminate\Database\UniqueConstraintViolationException::class,
                $exception->getPrevious(),
            );
        }
        $this->assertDatabaseCount('social_identities', 1);
        $this->assertDatabaseHas(
            'social_identities',
            ['user_id' => $user->id, 'provider_user_id' => 'google-original']
        );
    }

    public function testUnlinkAndDeviceRevocationRequireConfirmationForDirectCallers(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $user->socialIdentities()->create(['provider' => 'google', 'provider_user_id' => 'google-123']);
        $token = $user->createToken('Keep', ['account:read']);
        $confirmedAt = now()->getTimestamp() - Config::integer('auth.password_timeout');

        try {
            $this->app->make(UnlinkSocialIdentity::class)($user, 'google', $confirmedAt);
            $this->fail('Unlinking requires recent password confirmation.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('social_identities', 1);
        }
        try {
            $this->app->make(RevokeDeviceToken::class)($user, $token->accessToken->id, $confirmedAt);
            $this->fail('Revoking another device requires recent password confirmation.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('personal_access_tokens', 1);
        }
    }

    public function testBrowserSessionTokenCannotBeRevokedAsADeviceToken(): void
    {
        $actor = User::factory()->create();
        $actor->createToken('Keep', ['account:read']);

        try {
            $this->app->make(RevokeCurrentDeviceToken::class)($actor, new TransientToken());
            $this->fail('A session is not a revocable device token.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('This endpoint requires a device token.', $exception->getMessage());
        }
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }
}
