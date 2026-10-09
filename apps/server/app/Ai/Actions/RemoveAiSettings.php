<?php

namespace App\Ai\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RemoveAiSettings
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    public function __invoke(User $owner): void
    {
        DB::transaction(function () use ($owner): void {
            User::query()->whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $credential = $owner->aiCredential()->first();
            if (! $credential) {
                return;
            }
            $before = $credential->only(['provider', 'model']);
            $credential->delete();
            ($this->recordUserActivity)(
                $owner,
                $owner->id,
                ActivityEvent::AiSettingsRemoved,
                $credential->id,
                $before,
                force: true
            );
        });
    }
}
