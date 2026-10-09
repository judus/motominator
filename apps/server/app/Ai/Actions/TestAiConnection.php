<?php

namespace App\Ai\Actions;

use App\Ai\Agents\ConnectionCheck;
use App\Ai\Exceptions\AiOperationException;
use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\AiException;
use Throwable;

class TestAiConnection
{
    public function __invoke(User $owner): void
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        $credential = DB::transaction(function () use ($owner): AiCredential {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            if (! $owner->hasVerifiedEmail()) {
                throw new AuthorizationException();
            }
            $credential = $owner->aiCredential()->lockForUpdate()->first();
            if (! $credential) {
                throw ValidationException::withMessages(
                    ['api_key' => 'Save your AI settings before testing the connection.']
                );
            }

            return $credential;
        });
        $configuration = config('ai_byok.providers.' . $credential->provider);
        if (! is_array($configuration)) {
            throw ValidationException::withMessages(['provider' => 'This AI provider is no longer supported.']);
        }

        try {
            (new ConnectionCheck())->prompt(
                'Reply with OK.',
                provider: [Ai::build([
                    'driver' => $credential->provider,
                    'url' => $configuration['url'],
                    'key' => $credential->api_key,
                    'store' => false,
                    'models' => ['text' => ['default' => $credential->model]],
                ])
                ],
                timeout: 20,
            );
        } catch (AiException | ConnectionException | RequestException $exception) {
            Log::warning(
                'ai.connection_failed',
                ['user_id' => $owner->id, 'provider' => $credential->provider, 'model' => $credential->model]
                    + AiOperationException::diagnostics($exception),
            );
            // Provider exceptions may contain credentials or raw HTTP headers.
            throw ValidationException::withMessages(
                [
                    'connection' => 'Connection failed. Check your API key, model access, provider balance and '
                    . 'availability, then retry.'
                ]
            );
        } catch (Throwable $exception) {
            throw AiOperationException::unexpected($exception);
        } finally {
            Ai::flushState();
        }
    }
}
