<?php

namespace App\Logging;

use Illuminate\Log\Events\MessageLogged;
use Laravel\Pail\Handler;
use Monolog\DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;

class RedactingPailHandler extends Handler
{
    public function log(MessageLogged $messageLogged): void
    {
        $record = (new RedactSensitiveData())(new LogRecord(
            datetime: new DateTimeImmutable(true),
            channel: 'pail',
            level: Level::fromName($messageLogged->level),
            message: $messageLogged->message,
            context: $messageLogged->context,
        ));
        parent::log(new MessageLogged($messageLogged->level, $record->message, $record->context));
    }
}
