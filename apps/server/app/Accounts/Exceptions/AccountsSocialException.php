<?php

namespace App\Accounts\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Throwable;

final class AccountsSocialException extends RuntimeException implements ShouldntReport
{
    /**
     * @param 'unsupported_provider'|'provider_unavailable'|'identity_already_linked'
     *     |'final_sign_in_method'|'identity_conflict' $reason
     */
    private function __construct(
        string $message,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function unsupportedProvider(): self
    {
        return new self('This sign-in provider is not supported.', 'unsupported_provider');
    }

    public static function providerUnavailable(): self
    {
        return new self('This sign-in provider is not configured.', 'provider_unavailable');
    }

    public static function identityAlreadyLinked(): self
    {
        return new self('This sign-in identity is already linked to another account.', 'identity_already_linked');
    }

    public static function identityConflict(Throwable $previous): self
    {
        return new self('This sign-in identity cannot be linked to this account.', 'identity_conflict', $previous);
    }

    public static function finalSignInMethod(): self
    {
        return new self(
            'Set a password or link another provider before removing your final sign-in method.',
            'final_sign_in_method',
        );
    }
}
