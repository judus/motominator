<?php

namespace App\Ai\Actions;

use App\Ai\Agents\Copilot;
use App\Ai\Exceptions\AiCopilotException;
use App\Ai\Exceptions\AiOperationException;
use App\Garage\Actions\ReadGarageContext;
use App\Models\AiCredential;
use App\Models\User;
use Generator;
use Closure;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\Meta;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Ai;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class ReplyToCopilot
{
    public function __construct(
        private readonly CopilotConversations $conversations,
        private readonly ReadGarageContext $garage,
        private readonly ConversationStore $store,
    ) {
    }

    /**
     * @param Closure(): bool|null $cancelled
     * @return Generator<int, array{type: string, text?: string, message?: string}>
     */
    public function __invoke(
        User $actor,
        string $conversationId,
        string $message,
        ?Closure $cancelled = null,
    ): Generator {
        Validator::make(['message' => $message], ['message' => ['required', 'string', 'max:8000']])->validate();
        $conversation = $this->conversations->find($actor, $conversationId);
        $credential = DB::transaction(function () use ($actor): AiCredential {
            $current = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $current->hasVerifiedEmail()) {
                throw new AuthorizationException();
            }

            return $current->aiCredential()->lockForUpdate()->first()
                ?? throw ValidationException::withMessages(
                    ['ai' => 'Save your AI provider and key in Account → AI settings.']
                );
        });
        $actor = $credential->user;
        $provider = config('ai_byok.providers.' . $credential->provider);
        if (! is_array($provider) || ! isset($provider['url']) || ! is_string($provider['url'])) {
            throw ValidationException::withMessages(['ai' => 'The selected provider is no longer supported.']);
        }

        return $this->stream($actor, $conversation->id, $message, $credential, $provider['url'], $cancelled);
    }

    /**
     * @param Closure(): bool|null $cancelled
     * @return Generator<int, array{type: string, text?: string, message?: string}>
     */
    private function stream(
        User $actor,
        string $id,
        string $message,
        AiCredential $credential,
        string $url,
        ?Closure $cancelled,
    ): Generator {
        $lock = Cache::lock('copilot:' . $id, 600);
        if (! $lock->get()) {
            yield ['type' => 'error', 'message' => AiCopilotException::conversationBusy()->getMessage()];

            return;
        }
        try {
            // Deletion may have completed between authorization and consuming this generator.
            $conversation = $this->conversations->find($actor, $id);
            if ($conversation->title === 'New conversation') {
                $conversation->forceFill(['title' => Str::limit($message, 70)])->save();
            }
            $provider = Ai::build([
                'driver' => $credential->provider,
                'url' => $url,
                'key' => $credential->api_key,
                'store' => false,
                'models' => ['text' => ['default' => $credential->model]],
            ]);
            if (! $provider instanceof TextProvider) {
                throw AiCopilotException::unsupportedProvider();
            }
            $agent = new Copilot($actor, $this->garage);
            $response = $agent->continue($id, as: $actor)->stream($message, provider: [$provider], timeout: 90);
            $text = '';
            $messageId = null;
            foreach ($response as $event) {
                if ($cancelled !== null && $cancelled()) {
                    $prompt = new AgentPrompt(
                        $agent,
                        $message,
                        [],
                        $provider,
                        $credential->model,
                    );
                    $this->rememberInterrupted($actor, $id, $prompt, $text);

                    return;
                }
                if ($event instanceof TextDelta) {
                    $delta = $messageId !== null && $messageId !== $event->messageId
                        ? "\n\n" . $event->delta : $event->delta;
                    $messageId = $event->messageId;
                    $text .= $delta;
                    yield ['type' => 'text', 'text' => $delta];
                }
            }
            yield ['type' => 'done'];
        } catch (Throwable $exception) {
            Log::warning('ai.copilot_failed', [
                'user_id' => $actor->id,
                'conversation_id' => $id,
                'provider' => $credential->provider,
                'model' => $credential->model,
            ] + AiOperationException::diagnostics($exception));
            yield ['type' => 'error', 'message' => 'The AI reply did not complete. Check your AI settings and retry.'];
        } finally {
            Ai::flushState();
            $lock->release();
        }
    }

    private function rememberInterrupted(User $actor, string $id, AgentPrompt $prompt, string $text): void
    {
        $this->store->storeUserMessage(
            $id,
            $actor->getMorphClass(),
            $actor->id,
            Copilot::class,
            new UserMessage($prompt->prompt)
        );
        $this->store->storeAssistantMessage(
            $id,
            $actor->getMorphClass(),
            $actor->id,
            $prompt,
            new AgentResponse(
                'interrupted',
                $text,
                new TextUsage(),
                new Meta($prompt->provider->name(), $prompt->model)
            ),
            AiCopilotException::replyStopped(),
        );
    }
}
