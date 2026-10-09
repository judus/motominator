<?php

namespace App\Accounts\Actions;

use App\Accounts\Exceptions\AccountsSocialException;

class EnsureSocialProvider
{
    public function __invoke(string $provider, bool $requireConfiguration = true): void
    {
        if (! in_array($provider, ['google', 'github'], true)) {
            throw AccountsSocialException::unsupportedProvider();
        }
        if (! $requireConfiguration) {
            return;
        }
        if (blank(config("services.{$provider}.client_id")) || blank(config("services.{$provider}.client_secret"))) {
            throw AccountsSocialException::providerUnavailable();
        }
    }
}
