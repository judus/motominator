<?php

namespace App\Activity\Exceptions;

use LogicException;

final class ActivityRecordingException extends LogicException
{
    public static function transactionRequired(): self
    {
        return new self('Activity must be recorded inside the domain transaction.');
    }
}
