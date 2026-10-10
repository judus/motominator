<?php

namespace App\Ai\Storage;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Storage\DatabaseConversationStore;
use Throwable;

class SafeConversationStore extends DatabaseConversationStore
{
    /** @return array<string, mixed> */
    protected function metaFor(AgentResponse $response, ?Throwable $exception): array
    {
        return $exception === null
            ? parent::metaFor($response, null)
            : [...parent::metaFor($response, null), 'error' => 'The AI request did not complete.'];
    }
}
