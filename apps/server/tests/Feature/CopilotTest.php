<?php

namespace Tests\Feature;

use App\Ai\Actions\CopilotConversations;
use App\Ai\Actions\ReplyToCopilot;
use App\Ai\Agents\Copilot;
use App\Ai\Storage\SafeConversationStore;
use App\Ai\Tools\GetMotorcycleHistory;
use App\Garage\Actions\ReadGarageContext;
use App\Models\AiCredential;
use App\Models\CopilotConversation;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Laravel\Sanctum\Sanctum;
use Laravel\Telescope\Telescope;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PATH = '/api/v1/ai/conversations';

    public function testGuestsCannotAccessConversations(): void
    {
        $this->getJson(self::PATH)->assertUnauthorized();
        $this->postJson(self::PATH)->assertUnauthorized();
    }

    public function testConversationsAndMessagesArePrivateAndCannotBeAssignedToAnotherUser(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner, ['ai:read', 'ai:write', 'garage:read']);
        $this->postJson(self::PATH, ['participant_id' => $other->id])
            ->assertCreated()->assertJsonPath('data.title', 'New conversation')
            ->assertJsonMissingPath('data.participant_id')->assertHeader('Cache-Control', 'no-store, private');
        $conversation = CopilotConversation::query()->firstOrFail();
        $this->assertSame($owner->id, $conversation->participant_id);
        Sanctum::actingAs($other, ['ai:read', 'ai:write', 'garage:read']);
        $this->getJson(self::PATH)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::PATH . '/' . $conversation->id . '/messages')->assertNotFound();
        $this->postJson(self::PATH . '/' . $conversation->id . '/messages', ['message' => 'hello'])->assertNotFound();
        $this->deleteJson(self::PATH . '/' . $conversation->id)->assertNotFound();
        $this->assertDatabaseCount('agent_conversations', 1);
    }

    public function testNativeChatRequiresAiWriteAndGarageReadPermissions(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake(['hello'])->preventStrayPrompts();
        Sanctum::actingAs($credential->user, ['ai:read']);
        $this->postJson(self::PATH)->assertForbidden();
        $this->postJson(self::PATH . '/' . $conversation->id . '/messages', ['message' => 'hello'])->assertForbidden();
        Sanctum::actingAs($credential->user, ['ai:write']);
        $this->postJson(self::PATH . '/' . $conversation->id . '/messages', ['message' => 'hello'])->assertForbidden();
        Copilot::assertNeverPrompted();
    }

    public function testAnUnverifiedAccountCannotStartOrSendChat(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->postJson(self::PATH)->assertForbidden();
        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function testChatRequiresSavedByokSettingsWithoutGlobalFallback(): void
    {
        config(['ai.providers.openai.key' => 'global-key-must-not-be-used']);
        $owner = User::factory()->create();
        $conversation = app(CopilotConversations::class)->start($owner);
        Copilot::fake()->preventStrayPrompts();
        $this->actingAs($owner)->postJson(self::PATH . '/' . $conversation->id . '/messages', ['message' => 'hello'])
            ->assertUnprocessable()->assertJsonValidationErrors('ai');
        Copilot::assertNeverPrompted();
    }

    #[TestWith([''])]
    #[TestWith([['not a string']])]
    public function testInvalidMessagesAreRejectedBeforeCallingTheProvider(mixed $message): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake()->preventStrayPrompts();
        $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => $message],
        )->assertUnprocessable()->assertJsonValidationErrors('message');
        Copilot::assertNeverPrompted();
    }

    public function testOversizedMessagesAreRejected(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => str_repeat('x', 8001)],
        )->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function testStreamingUsesTheOwnersModelAndPersistsTheExchange(): void
    {
        $credential = AiCredential::factory()->create(['model' => 'owners-model', 'api_key' => 'private-key-1234']);
        $conversation = app(CopilotConversations::class)->start($credential->user);
        $level = DB::transactionLevel();
        Copilot::fake(function () use ($level): string {
            $this->assertSame($level, DB::transactionLevel());

            return 'Your garage is ready.';
        })->preventStrayPrompts();
        Telescope::startRecording(false);
        $response = $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => 'Tell me about my garage'],
        )->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');
        $stream = $response->streamedContent();
        $this->assertStringContainsString('"type":"done"', $stream);
        $this->assertStringContainsString('"type":"text"', $stream);
        $this->assertStringNotContainsString('private-key-1234', $stream);
        $this->assertFalse(Telescope::isRecording());
        Copilot::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'owners-model'
            && $prompt->provider->providerCredentials()['key'] === 'private-key-1234');
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Tell me about my garage',
        ]);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Your garage is ready.',
        ]);
        $history = $this->getJson(self::PATH . '/' . $conversation->id . '/messages')
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.steps');
        $this->assertStringNotContainsString('private-key-1234', $this->stringValue($history->getContent()));
    }

    public function testToolCallingUsesOnlyTheOwnersGarageAndContinuesTheConversation(): void
    {
        $credential = AiCredential::factory()->create();
        $bike = Motorcycle::factory()->for($credential->user)->create(['nickname' => 'My bike']);
        Motorcycle::factory()->create(['nickname' => 'Another owners private bike']);
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake([
            new ToolCall('list-bikes', 'ListMyMotorcycles', ['page' => 1]),
            'I found your bike.',
            'We discussed your garage.',
        ])->preventStrayPrompts();
        $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => 'What bikes do I own?'],
        )->assertOk()->streamedContent();
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $stored = $this->stringValue(
            DB::table('agent_conversation_messages')->where('role', 'assistant')->value('steps')
        );
        $this->assertStringContainsString('My bike', $stored);
        $this->assertStringNotContainsString('Another owners private bike', $stored);
        $this->assertDatabaseHas('motorcycles', ['id' => $bike->id, 'nickname' => 'My bike']);
        $this->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => 'What did we discuss?'],
        )->assertOk()->streamedContent();
        $this->assertDatabaseCount('agent_conversation_messages', 4);
        Copilot::assertPromptedTimes(2);
    }

    public function testProviderFailureReturnsASafeErrorAndReleasesTheConversation(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake(fn (): never => throw new RuntimeException('secret-key and raw provider payload'))
            ->preventStrayPrompts();
        $stream = $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => 'hello'],
        )->assertOk()->streamedContent();
        $this->assertStringContainsString('"type":"error"', $stream);
        $this->assertStringNotContainsString('secret-key', $stream);
        $lock = Cache::lock('copilot:' . $conversation->id, 10);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function testAnInterruptedReplyRetainsPartialTextAndReleasesItsLock(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake(['This is a longer reply.'])->preventStrayPrompts();
        $cancel = false;
        // The callback captures a mutable signal just as the HTTP adapter observes its connection.
        $events = app(ReplyToCopilot::class)(
            $credential->user,
            $conversation->id,
            'hello',
            function () use (&$cancel): bool {
                return $cancel;
            },
        );
        $partial = '';
        foreach ($events as $event) {
            if ($event['type'] === 'text') {
                $partial .= $event['text'] ?? '';
                $cancel = true;
            }
        }
        $this->assertNotSame('', $partial);
        $this->assertDatabaseHas('agent_conversation_messages', [
            'conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => $partial, 'status' => 'failed',
        ]);
        $this->assertDatabaseCount('agent_conversation_messages', 2);
        $lock = Cache::lock('copilot:' . $conversation->id, 10);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function testFailureMetadataCannotPersistRawProviderExceptions(): void
    {
        $owner = User::factory()->create();
        $conversation = app(CopilotConversations::class)->start($owner);
        $provider = Ai::build(['driver' => 'openai', 'key' => 'unused-test-key']);
        $this->assertInstanceOf(TextProvider::class, $provider);
        $prompt = new AgentPrompt(
            new Copilot($owner, app(ReadGarageContext::class)),
            'hello',
            [],
            $provider,
            'test-model'
        );
        app(SafeConversationStore::class)->storeAssistantMessage(
            $conversation->id,
            $owner->getMorphClass(),
            $owner->id,
            $prompt,
            new AgentResponse('test-turn', 'Partial reply', new TextUsage(), new Meta()),
            new RuntimeException('secret provider body'),
        );
        $meta = $this->stringValue(DB::table('agent_conversation_messages')->value('meta'));
        $this->assertStringNotContainsString('secret provider body', $meta);
        $this->assertStringContainsString('The AI request did not complete.', $meta);
    }

    public function testConcurrentRepliesAndDeletionAreBlocked(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        $lock = Cache::lock('copilot:' . $conversation->id, 10);
        $this->assertTrue($lock->get());
        Copilot::fake()->preventStrayPrompts();
        try {
            $stream = $this->actingAs($credential->user)->postJson(
                self::PATH . '/' . $conversation->id . '/messages',
                ['message' => 'hello'],
            )->assertOk()->streamedContent();
            $this->assertStringContainsString('still processing', $stream);
            $this->deleteJson(self::PATH . '/' . $conversation->id)->assertConflict();
            Copilot::assertNeverPrompted();
        } finally {
            $lock->release();
        }
    }

    public function testDeletingAConversationRemovesItsMessages(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        Copilot::fake(['hello'])->preventStrayPrompts();
        $this->actingAs($credential->user)->postJson(
            self::PATH . '/' . $conversation->id . '/messages',
            ['message' => 'hello'],
        )->assertOk()->streamedContent();
        $this->deleteJson(self::PATH . '/' . $conversation->id)->assertNoContent();
        $this->assertDatabaseCount('agent_conversation_messages', 0);
        $this->assertDatabaseCount('agent_conversations', 0);
    }

    public function testGarageToolsRejectForeignMotorcyclesAndReportBoundedHistory(): void
    {
        $owner = User::factory()->create();
        $foreign = Motorcycle::factory()->create();
        $garage = app(ReadGarageContext::class);
        $tool = new GetMotorcycleHistory($owner, $garage);
        $this->assertSame(
            'That motorcycle is not available. Use ListMyMotorcycles to select an accessible motorcycle.',
            $tool->handle(new Request(['motorcycle_id' => $foreign->id])),
        );
        $bike = Motorcycle::factory()->for($owner)->create();
        MaintenanceRecord::factory()->for($bike)->count(31)->create();
        $history = $garage->motorcycle($owner, $bike->id);
        $this->assertSame(31, $history['maintenance_count']);
        $this->assertCount(30, $history['recent_maintenance']);
    }

    public function testAStaleVerifiedActorCannotSendAnAiRequest(): void
    {
        $credential = AiCredential::factory()->create();
        $conversation = app(CopilotConversations::class)->start($credential->user);
        $actor = $credential->user;
        $actor->fresh()?->forceFill(['email_verified_at' => null])->save();
        Copilot::fake()->preventStrayPrompts();
        try {
            app(ReplyToCopilot::class)($actor, $conversation->id, 'hello');
            $this->fail('Revoked verification must prevent a new AI request.');
        } catch (AuthorizationException) {
            Copilot::assertNeverPrompted();
        }
    }

    public function testBrowserChatWritesRequireCsrfProtection(): void
    {
        $this->app->instance('env', 'local');
        $this->actingAs(User::factory()->create())->withHeaders([
            'Origin' => 'http://localhost:5173', 'Referer' => 'http://localhost:5173/copilot',
        ])->postJson(self::PATH)->assertStatus(419);
        $this->assertDatabaseCount('agent_conversations', 0);
    }
}
