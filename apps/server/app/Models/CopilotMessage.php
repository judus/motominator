<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $role
 * @property string $content
 * @property string $status
 * @property Carbon $created_at
 */
#[WithoutIncrementing]
class CopilotMessage extends Model
{
    protected $keyType = 'string';

    public function getTable(): string
    {
        return Config::string('ai.conversations.tables.messages', 'agent_conversation_messages');
    }
}
