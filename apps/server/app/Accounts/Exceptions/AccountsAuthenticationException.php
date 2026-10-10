<?php

namespace App\Accounts\Exceptions;

use RuntimeException;

final class AccountsAuthenticationException extends RuntimeException
{
    public static function unexpectedUser(): self
    {
        return new self('Password recovery requires an application user.');
    }

    public static function invalidSecret(): self
    {
        return new self('Invalid two-factor secret configuration.');
    }
}
