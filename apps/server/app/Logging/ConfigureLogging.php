<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger as MonologLogger;

class ConfigureLogging
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();
        if (! $monolog instanceof MonologLogger) {
            return;
        }
        $monolog->pushProcessor(new RedactSensitiveData());
        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof AbstractProcessingHandler) {
                $handler->pushProcessor(new RedactSensitiveData());
                if (config('logging.json', true)) {
                    $handler->setFormatter(new JsonFormatter());
                }
            }
        }
    }
}
