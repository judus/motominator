<?php

namespace App\Ai\Exceptions;

use RuntimeException;

final class AiInvoiceExtractionException extends RuntimeException
{
    public static function ownerUnavailable(): self
    {
        return new self('Invoice owner unavailable.');
    }

    public static function settingsUnavailable(): self
    {
        return new self('BYOK settings unavailable.');
    }

    public static function structuredResponseMissing(): self
    {
        return new self('No structured invoice result.');
    }
}
