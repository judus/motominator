<?php

namespace App\Garage\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class GarageInvoiceException extends RuntimeException implements ShouldntReport
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function draftChanged(): self
    {
        return new self('The draft changed. Reload it before continuing.', 'draft_changed');
    }

    public static function draftAlreadyExists(): self
    {
        return new self('This import already has a draft.', 'draft_already_exists');
    }
}
