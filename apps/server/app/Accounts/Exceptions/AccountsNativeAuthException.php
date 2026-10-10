<?php

namespace App\Accounts\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class AccountsNativeAuthException extends RuntimeException implements ShouldntReport
{
    /** @param 'expired_intent'|'invalid_exchange' $reason */
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function expiredIntent(): self
    {
        return new self('The native sign-in request has expired.', 'expired_intent');
    }

    public static function invalidExchange(): self
    {
        return new self('The native sign-in request or verifier is invalid.', 'invalid_exchange');
    }
}
