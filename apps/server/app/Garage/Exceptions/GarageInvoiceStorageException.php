<?php

namespace App\Garage\Exceptions;

use RuntimeException;

final class GarageInvoiceStorageException extends RuntimeException
{
    public static function writeFailed(): self
    {
        return new self('Invoice storage failed.');
    }
}
