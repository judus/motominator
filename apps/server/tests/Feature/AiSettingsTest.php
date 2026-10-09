<?php

namespace Tests\Feature;

use App\Ai\Agents\ConnectionCheck;
use App\Ai\Actions\TestAiConnection;
use App\Ai\Exceptions\AiOperationException;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PATH = '/api/v1/ai/settings';

    public function testConnectionChecksRejectRevokedVerificationOnAStaleActor(): void
    {
        ConnectionCheck::fake(['OK'])->preventStrayPrompts();
        $credential = AiCredential::factory()->create();
        $owner = $credential->user;
        User::query()->findOrFail($owner->id)->forceFill(['email_verified_at' => null])->save();

        try {
            app(TestAiConnection::class)($owner);
            $this->fail('A stale verified actor must not send an AI request.');
        } catch (AuthorizationException) {
            ConnectionCheck::assertNeverPrompted();
        }
    }

    public function testConnectionChecksReleaseAuthorizationTransactionBeforePrompting(): void
    {
        $credential = AiCredential::factory()->create();
        $level = DB::transactionLevel();
        ConnectionCheck::fake(function () use ($level): string {
            $this->assertSame($level, DB::transactionLevel());

            return 'OK';
        })->preventStrayPrompts();

        app(TestAiConnection::class)($credential->user);

        ConnectionCheck::assertPromptedTimes(1);
    }

    public function testGuestsCannotReadWriteTestOrRemoveCredentials(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized();
        $this->putJson(self::PATH)->assertUnauthorized();
        $this->postJson(self::PATH . '/test')->assertUnauthorized();
        $this->deleteJson(self::PATH)->assertUnauthorized();
    }

    public function testANewAccountHasNoAiCredentialsOrGlobalKeyFallback(): void
    {
        config(['ai.providers.openai.key' => 'global-key-must-not-be-used']);
        ConnectionCheck::fake()->preventStrayPrompts();
        $this->actingAs(User::factory()->create())->getJson(self::PATH)
            ->assertOk()->assertJsonPath('data', null)->assertJsonCount(3, 'providers')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson(self::PATH . '/test')->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');
        ConnectionCheck::assertNeverPrompted();
    }

    public function testSavingEncryptsTheKeyAndNeverReturnsItOrAcceptsAnotherOwner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $key = 'test-api-key-for-owner-1234';
        $response = $this->actingAs($owner)->putJson(self::PATH, [
            'provider' => 'openai',
            'model' => 'test-model',
            'api_key' => $key,
            'user_id' => $other->id,
            'url' => 'https://attacker.invalid',
        ])->assertOk()->assertJsonPath(
            'data.key_hint',
            '••••1234'
        )->assertJsonMissingPath(
            'data.api_key'
        );
        $credential = $owner->aiCredential()->firstOrFail();
        $this->assertSame($key, $credential->api_key);
        $this->assertStringNotContainsString($key, $this->stringValue($credential->getRawOriginal('api_key')));
        $this->assertArrayNotHasKey('api_key', $credential->toArray());
        $this->assertStringNotContainsString($key, $this->stringValue($response->getContent()));
        $this->assertNull($other->aiCredential()->first());
        $this->assertDatabaseCount('ai_credentials', 1);
    }

    public function testModelEditsKeepTheKeyAndKeyReplacementUpdatesTheHint(): void
    {
        $credential = AiCredential::factory()->create();
        $this->actingAs($credential->user)->putJson(self::PATH, ['provider' => 'openai', 'model' => 'another-model'])
            ->assertOk()->assertJsonPath('data.model', 'another-model');
        $this->assertSame('test-key-not-a-real-secret', $credential->refresh()->api_key);
        $this->putJson(
            self::PATH,
            ['provider' => 'openai', 'model' => 'another-model', 'api_key' => 'replacement-test-key-5678']
        )
            ->assertOk()->assertJsonPath('data.key_hint', '••••5678');
        $this->assertSame('replacement-test-key-5678', $credential->refresh()->api_key);
        $this->assertDatabaseCount('ai_credentials', 1);
    }

    public function testChangingProviderRequiresANewKey(): void
    {
        $credential = AiCredential::factory()->create();
        $data = ['provider' => 'anthropic', 'model' => 'another-model'];
        $this->actingAs($credential->user)->putJson(self::PATH, $data)->assertUnprocessable()
            ->assertJsonPath('errors.api_key.0', 'Enter an API key for the selected provider.');
        $this->assertSame('openai', $credential->refresh()->provider);
        $this->putJson(self::PATH, $data + ['api_key' => 'anthropic-test-key-5678'])->assertOk()->assertJsonPath(
            'data.provider',
            'anthropic'
        );
        $this->assertSame('anthropic-test-key-5678', $credential->refresh()->api_key);
    }

    public function testUsersReadAndRemoveOnlyTheirOwnCredentials(): void
    {
        $mine = AiCredential::factory()->create();
        $other = AiCredential::factory()->create(['provider' => 'gemini', 'model' => 'other-model']);
        $this->actingAs($mine->user)->getJson(self::PATH)->assertOk()->assertJsonPath('data.model', 'test-model');
        $this->deleteJson(self::PATH)->assertOk()->assertJsonPath('data', null);
        $this->assertModelMissing($mine);
        $this->assertModelExists($other);
        $this->deleteJson(self::PATH)->assertOk();
    }

    public function testUnverifiedAccountsCanReadAndRemoveButCannotSaveOrTest(): void
    {
        $credential = AiCredential::factory()->for(User::factory()->unverified())->create();
        $this->actingAs($credential->user)->getJson(self::PATH)->assertOk();
        $this->putJson(self::PATH)->assertForbidden();
        $this->postJson(self::PATH . '/test')->assertForbidden();
        $this->deleteJson(self::PATH)->assertOk();
        $this->assertModelMissing($credential);
    }

    public function testNativeTokensEnforceSeparateAiReadAndWriteAbilities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['account:read', 'garage:write']);
        $this->getJson(self::PATH)->assertForbidden();
        $this->putJson(self::PATH)->assertForbidden();
        $this->postJson(self::PATH . '/test')->assertForbidden();
        $this->deleteJson(self::PATH)->assertForbidden();
        Sanctum::actingAs($user, ['ai:read']);
        $this->getJson(self::PATH)->assertOk();
        $this->putJson(self::PATH)->assertForbidden();
        Sanctum::actingAs($user, ['ai:write']);
        $this->putJson(
            self::PATH,
            ['provider' => 'openai', 'model' => 'test-model', 'api_key' => 'native-test-key-1234']
        )->assertOk();
        $this->deleteJson(self::PATH)->assertOk();
    }

    public function testMissingFieldsAndInitialKeyAreRejected(): void
    {
        $this->actingAs(User::factory()->create())->putJson(self::PATH)->assertUnprocessable()
            ->assertJsonValidationErrors(['provider', 'model']);
        $this->putJson(self::PATH, ['provider' => 'openai', 'model' => 'test-model'])->assertUnprocessable()
            ->assertJsonValidationErrors('api_key');
        $this->assertDatabaseCount('ai_credentials', 0);
    }

    #[TestWith(['provider', 'unknown'])]
    #[TestWith(['model', 'invalid model'])]
    #[TestWith(['model', '<script>'])]
    #[TestWith(['model', ''])]
    #[TestWith(['api_key', 'short'])]
    #[TestWith(['api_key', "key-with-newline-1234\nextra"])]
    public function testInvalidSettingsAreRejectedWithoutPersisting(string $field, string $value): void
    {
        $data = ['provider' => 'openai', 'model' => 'test-model', 'api_key' => 'valid-test-key-1234'];
        $data[$field] = $value;
        $this->actingAs(User::factory()->create())->putJson(
            self::PATH,
            $data
        )->assertUnprocessable()->assertJsonValidationErrors(
            $field
        );
        $this->assertDatabaseCount('ai_credentials', 0);
    }

    #[TestWith(['openai'])]
    #[TestWith(['anthropic'])]
    #[TestWith(['gemini'])]
    public function testConnectionChecksUseTheUsersSavedProviderKeyAndModel(string $provider): void
    {
        ConnectionCheck::fake(['OK'])->preventStrayPrompts();
        $credential = AiCredential::factory()->create(['provider' => $provider]);
        config(['ai.providers.' . $provider . '.key' => 'global-key-must-not-be-used']);
        $this->actingAs($credential->user)->postJson(self::PATH . '/test')->assertOk()
            ->assertJsonPath('message', 'Connection successful. The selected model accepted a text request.');
        ConnectionCheck::assertPrompted(function (AgentPrompt $prompt): bool {
            $this->assertSame('test-key-not-a-real-secret', $prompt->provider->providerCredentials()['key']);
            $this->assertSame('test-model', $prompt->model);
            $this->assertSame(20, $prompt->timeout);

            return true;
        });
        $this->assertSame('global-key-must-not-be-used', config('ai.providers.' . $provider . '.key'));
    }

    public function testOpenaiTransportUsesOnlyTheOwnersKeyAndDisablesResponseStorage(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'id' => 'response_test',
                'model' => 'test-model',
                'status' => 'completed',
                'output' => [
                    [
                        'type' => 'message',
                        'role' => 'assistant',
                        'content' => [['type' => 'output_text', 'text' => 'OK']]
                    ],
                ],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 1, 'total_tokens' => 11],
            ])
        ]);
        $credential = AiCredential::factory()->create();
        $this->actingAs($credential->user)->postJson(self::PATH . '/test')->assertOk();
        Http::assertSent(function (Request $request): bool {
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-key-not-a-real-secret'));
            $this->assertSame('test-model', $request['model']);
            $this->assertFalse($request['store']);

            return true;
        });
        Http::assertSentCount(1);
    }

    public function testProviderErrorsAreSanitizedAndConnectionTestsAreThrottled(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid key test-key-not-a-real-secret']], 401)]);
        $credential = AiCredential::factory()->create();
        $this->actingAs($credential->user);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->postJson(self::PATH . '/test')->assertUnprocessable()->assertJsonValidationErrors(
                'connection'
            );
            $this->assertStringNotContainsString(
                'test-key-not-a-real-secret',
                $this->stringValue($response->getContent())
            );
        }
        $this->postJson(self::PATH . '/test')->assertTooManyRequests();
        Http::assertSentCount(3);
    }

    public function testConnectionInternalErrorsAreReportedAsServerErrorsWithoutPrivateMessages(): void
    {
        $credential = AiCredential::factory()->create();
        ConnectionCheck::fake(fn () => throw new \TypeError('private-connection-sentinel'))->preventStrayPrompts();
        Exceptions::fake();

        $response = $this->actingAs($credential->user)->postJson(self::PATH . '/test')->assertInternalServerError();

        $this->assertStringNotContainsString(
            'private-connection-sentinel',
            $this->stringValue($response->getContent())
        );
        Exceptions::assertReported(
            fn (AiOperationException $exception): bool => $exception->context()['failure_class'] === \TypeError::class
        );
        ConnectionCheck::assertPromptedTimes(1);
    }

    public function testSecretRoutesDisableTelescopeRecordingEvenWhenValidationFails(): void
    {
        Telescope::startRecording(false);
        $this->actingAs(User::factory()->create())->putJson(
            self::PATH,
            ['api_key' => 'invalid']
        )->assertUnprocessable();
        $this->assertFalse(Telescope::isRecording());
    }

    public function testBrowserSavesRequireCsrfProtection(): void
    {
        $user = User::factory()->create();
        $this->app->instance('env', 'local');
        $this->actingAs($user)->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Referer' => 'http://localhost:5173/account',
        ])->putJson(
            self::PATH,
            ['provider' => 'openai', 'model' => 'test-model', 'api_key' => 'test-key-not-a-real-secret']
        )
            ->assertStatus(419);
        $this->assertDatabaseCount('ai_credentials', 0);
    }

    public function testConnectionChecksDoNotReuseAnotherAccountsCredentialsInTheSameProcess(): void
    {
        ConnectionCheck::fake(['OK', 'OK'])->preventStrayPrompts();
        $first = AiCredential::factory()->create(['api_key' => 'first-owner-key-1234', 'model' => 'first-model']);
        $second = AiCredential::factory()->create(['api_key' => 'second-owner-key-5678', 'model' => 'second-model']);
        Sanctum::actingAs($first->user, ['ai:write']);
        $this->postJson(self::PATH . '/test')->assertOk();
        Sanctum::actingAs($second->user, ['ai:write']);
        $this->postJson(self::PATH . '/test')->assertOk();
        ConnectionCheck::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'first-model'
            && $prompt->provider->providerCredentials()['key'] === 'first-owner-key-1234');
        ConnectionCheck::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'second-model'
            && $prompt->provider->providerCredentials()['key'] === 'second-owner-key-5678');
        ConnectionCheck::assertPromptedTimes(2);
    }
}
