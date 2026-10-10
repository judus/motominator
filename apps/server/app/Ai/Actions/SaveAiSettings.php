<?php

namespace App\Ai\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Models\AiCredential;
use App\Models\User;
use App\Support\Input;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveAiSettings
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(User $owner, #[\SensitiveParameter] array $input): AiCredential
    {
        if (! $owner->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        $data = Input::object(Validator::make($input, [
            'provider' => [
                'required',
                'string',
                Rule::in(
                    array_keys(
                        Config::array('ai_byok.providers')
                    )
                ),
            ],
            'model' => ['required', 'string', 'max:100', 'regex:/\A[a-zA-Z0-9][a-zA-Z0-9._:\/-]*\z/'],
            'api_key' => ['nullable', 'string', 'min:16', 'max:512', 'regex:/\A[\x21-\x7E]+\z/'],
        ])->validate());

        return DB::transaction(function () use ($owner, $data): AiCredential {
            $owner = User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            if (! $owner->hasVerifiedEmail()) {
                throw new AuthorizationException();
            }
            $credential = $owner->aiCredential()->first();
            $before = $credential?->only(['provider', 'model']) ?? [];
            $key = isset($data['api_key']) ? Input::string($data['api_key'], 'api_key') : null;
            if (! $key && (! $credential || $credential->provider !== $data['provider'])) {
                throw ValidationException::withMessages(['api_key' => 'Enter an API key for the selected provider.']);
            }

            $attributes = ['provider' => $data['provider'], 'model' => $data['model']];
            if ($key) {
                $attributes += ['api_key' => $key, 'key_hint' => '••••' . substr($key, -4)];
            }

            $saved = $owner->aiCredential()->updateOrCreate([], $attributes);
            ($this->recordUserActivity)(
                $owner,
                $owner->id,
                ActivityEvent::AiSettingsSaved,
                $saved->id,
                $before,
                $saved->only(
                    ['provider', 'model']
                ),
                force: (bool) $key
            );

            return $saved;
        });
    }
}
