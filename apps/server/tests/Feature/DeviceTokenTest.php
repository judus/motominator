<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testDeviceLoginIssuesANamedExpiringTokenWithLimitedPermissions(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $response = $this->postJson(
            '/api/v1/auth/tokens',
            ['email' => $user->email, 'password' => 'password', 'device_name' => 'My Android']
        )->assertCreated();
        $token = PersonalAccessToken::findToken($this->stringValue($response->json('token')));
        $this->assertInstanceOf(PersonalAccessToken::class, $token);
        $this->assertNotNull($token->expires_at);
        $this->assertSame('My Android', $token->name);
        $this->assertSame(
            ['account:read', 'account:write', 'garage:read', 'garage:write', 'ai:read', 'ai:write'],
            $token->abilities,
        );
        $this->assertSame(now()->addDays(30)->timestamp, $token->expires_at->timestamp);
        $this->withToken($this->stringValue($response->json('token')))->getJson(
            '/api/v1/user'
        )->assertOk()->assertJsonPath(
            'id',
            $user->id
        );
    }

    public function testInvalidCredentialsDoNotIssueTokensAndAreThrottled(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(
                '/api/v1/auth/tokens',
                ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Android']
            )->assertUnprocessable();
        }
        $this->postJson(
            '/api/v1/auth/tokens',
            ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Android']
        )->assertTooManyRequests();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function testTwoFactorLoginIssuesNoTokenUntilTheChallengeIsValidated(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create(
            ['two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret), 'two_factor_confirmed_at' => now()]
        );
        $data = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Android'];
        $this->postJson('/api/v1/auth/tokens', $data)->assertStatus(202)->assertJsonPath('two_factor', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/tokens', $data + ['code' => 'invalid'])->assertUnprocessable();
        $this->postJson(
            '/api/v1/auth/tokens',
            $data + ['code' => (new Google2FA())->getCurrentOtp($secret)]
        )->assertCreated();
    }

    public function testNativeRecoveryCodesAreSingleUse(): void
    {
        $user = User::factory()->create(
            [
                'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                    'JBSWY3DPEHPK3PXP'
                ),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                    json_encode(['recovery-code'])
                )
            ]
        );
        $data = [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Android',
            'recovery_code' => 'recovery-code'
        ];
        $this->postJson('/api/v1/auth/tokens', $data)->assertCreated();
        $this->postJson('/api/v1/auth/tokens', $data)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function testExpiredAndRevokedTokensCannotLoadTheCurrentUser(): void
    {
        $user = User::factory()->create();
        $expired = $user->createToken('Expired', ['account:read'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
        Auth::forgetGuards();
        $revoked = $user->createToken('Revoked', ['account:read']);
        $revoked->accessToken->delete();
        $this->withToken($revoked->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function testTokensWithoutAccountReadPermissionAreRejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Other', ['other']);
        $this->withToken($token->plainTextToken)->getJson('/api/v1/user')->assertForbidden();
    }

    public function testNativeLogoutRevokesOnlyItsCurrentToken(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Current', ['account:read']);
        $other = $user->createToken('Other', ['account:read']);
        $this->withToken($current->plainTextToken)->deleteJson('/api/v1/auth/token')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function testDeviceRevocationRequiresConfirmationAndOwnership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('Mine', ['account:read']);
        $foreign = $other->createToken('Not mine', ['account:read']);
        $this->actingAs($user)->getJson('/user/devices')->assertOk()->assertJsonCount(1)->assertJsonMissingPath(
            '0.token'
        );
        $this->deleteJson('/user/devices/' . $token->accessToken->id)->assertStatus(423);
        $this->withSession(['auth.password_confirmed_at' => time()])->deleteJson(
            '/user/devices/' . $foreign->accessToken->id
        )->assertNotFound();
        $this->deleteJson('/user/devices/' . $token->accessToken->id)->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);
    }
}
