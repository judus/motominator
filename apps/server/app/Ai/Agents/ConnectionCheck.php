<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[MaxTokens(128)]
class ConnectionCheck implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'This is a connection test. Reply with OK.';
    }
}
