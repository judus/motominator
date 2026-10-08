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

    public function test_device_login_issues_a_named_expiring_token_with_limited_permissions(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $response = $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'password', 'device_name' => 'My Android'])->assertCreated();
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame('My Android', $token->name);
        $this->assertSame(['account:read'], $token->abilities);
        $this->assertSame(now()->addDays(30)->timestamp, $token->expires_at->timestamp);
        $this->withToken($response->json('token'))->getJson('/api/v1/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_invalid_credentials_do_not_issue_tokens_and_are_throttled(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Android'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'wrong', 'device_name' => 'Android'])->assertTooManyRequests();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_two_factor_login_issues_no_token_until_the_challenge_is_validated(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create(['two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret), 'two_factor_confirmed_at' => now()]);
        $data = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Android'];
        $this->postJson('/api/v1/auth/tokens', $data)->assertStatus(202)->assertJsonPath('two_factor', true);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/v1/auth/tokens', $data + ['code' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/tokens', $data + ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertCreated();
    }

    public function test_native_recovery_codes_are_single_use(): void
    {
        $user = User::factory()->create(['two_factor_secret' => Fortify::currentEncrypter()->encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-code']))]);
        $data = ['email' => $user->email, 'password' => 'password', 'device_name' => 'Android', 'recovery_code' => 'recovery-code'];
        $this->postJson('/api/v1/auth/tokens', $data)->assertCreated();
        $this->postJson('/api/v1/auth/tokens', $data)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_expired_and_revoked_tokens_cannot_load_the_current_user(): void
    {
        $user = User::factory()->create();
        $expired = $user->createToken('Expired', ['account:read'], now()->subMinute());
        $this->withToken($expired->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
        Auth::forgetGuards();
        $revoked = $user->createToken('Revoked', ['account:read']);
        $revoked->accessToken->delete();
        $this->withToken($revoked->plainTextToken)->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_tokens_without_account_read_permission_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Other', ['other']);
        $this->withToken($token->plainTextToken)->getJson('/api/v1/user')->assertForbidden();
    }

    public function test_native_logout_revokes_only_its_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('Current', ['account:read']);
        $other = $user->createToken('Other', ['account:read']);
        $this->withToken($current->plainTextToken)->deleteJson('/api/v1/auth/token')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_device_revocation_requires_confirmation_and_ownership(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $token = $user->createToken('Mine', ['account:read']);
        $foreign = $other->createToken('Not mine', ['account:read']);
        $this->actingAs($user)->getJson('/user/devices')->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.token');
        $this->deleteJson('/user/devices/'.$token->accessToken->id)->assertStatus(423);
        $this->withSession(['auth.password_confirmed_at' => time()])->deleteJson('/user/devices/'.$foreign->accessToken->id)->assertNotFound();
        $this->deleteJson('/user/devices/'.$token->accessToken->id)->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $foreign->accessToken->id]);

    }
}
