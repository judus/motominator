<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property string $id
 * @property string $participant_type
 * @property int $participant_id
 * @property string $title
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[WithoutIncrementing]
class CopilotConversation extends Model
{
    protected $keyType = 'string';

    public function getTable(): string
    {
        return Config::string('ai.conversations.tables.conversations', 'agent_conversations');
    }

    /** @return HasMany<CopilotMessage, $this> */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(CopilotMessage::class, 'conversation_id');
    }
}
