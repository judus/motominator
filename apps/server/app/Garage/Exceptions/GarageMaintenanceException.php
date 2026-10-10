<?php

namespace App\Garage\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

final class GarageMaintenanceException extends RuntimeException implements ShouldntReport
{
    private function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function occurrenceSkipped(): self
    {
        return new self('A skipped occurrence cannot be fulfilled.', 'occurrence_skipped');
    }

    public static function actionAlreadyUsed(): self
    {
        return new self('This action already fulfilled another occurrence of this task.', 'action_already_used');
    }
}
