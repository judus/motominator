<?php

namespace App\Ai\Actions;

use App\Ai\Exceptions\AiCopilotException;
use App\Models\CopilotConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CopilotConversations
{
    /** @return Builder<CopilotConversation> */
    public function ownedBy(User $actor): Builder
    {
        return CopilotConversation::query()->where('participant_type', $actor->getMorphClass())
            ->where('participant_id', $actor->id);
    }

    public function find(User $actor, string $id): CopilotConversation
    {
        return $this->ownedBy($actor)->findOrFail($id);
    }

    public function start(User $actor): CopilotConversation
    {
        $actor = User::query()->findOrFail($actor->id);
        if (! $actor->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }
        $conversation = new CopilotConversation();
        $conversation->forceFill([
            'id' => (string) Str::uuid7(),
            'participant_type' => $actor->getMorphClass(),
            'participant_id' => $actor->id,
            'title' => 'New conversation',
        ])->save();

        return $conversation;
    }

    public function remove(User $actor, string $id): void
    {
        $conversation = $this->find($actor, $id);
        $lock = Cache::lock('copilot:' . $id, 600);
        if (! $lock->get()) {
            throw AiCopilotException::conversationBusy();
        }
        try {
            DB::transaction(function () use ($conversation): void {
                $conversation->chatMessages()->delete();
                $conversation->delete();
            });
        } finally {
            $lock->release();
        }
    }
}
