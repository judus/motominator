<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    protected function stringValue(mixed $value): string
    {
        $this->assertIsString($value);

        return $value;
    }

    protected function integerValue(mixed $value): int
    {
        $this->assertIsInt($value);

        return $value;
    }

    /** @param array<array-key, mixed> $parameters */
    protected function pendingCommand(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }
}
