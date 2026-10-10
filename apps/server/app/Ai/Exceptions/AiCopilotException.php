<?php

namespace App\Ai\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class AiCopilotException extends RuntimeException implements ShouldntReport
{
    public static function unsupportedProvider(): self
    {
        return new self('The selected provider does not support chat.');
    }

    public static function replyStopped(): self
    {
        return new self('The rider stopped this reply.');
    }

    public static function conversationBusy(): self
    {
        return new self('This conversation is still processing a reply. Please retry shortly.');
    }
}
