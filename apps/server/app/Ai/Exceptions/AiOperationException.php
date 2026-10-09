<?php

namespace App\Ai\Exceptions;

use RuntimeException;
use Throwable;

final class AiOperationException extends RuntimeException
{
    /** @param array<string, mixed> $diagnostics */
    private function __construct(private readonly array $diagnostics)
    {
        parent::__construct('An internal error prevented the AI operation from completing.');
    }

    /** Retain locations without retaining a private provider payload in the queue's exception chain. */
    public static function unexpected(Throwable $exception): self
    {
        return new self(self::diagnostics($exception));
    }

    /** @return array<string, mixed> */
    public static function diagnostics(Throwable $exception): array
    {
        return $exception instanceof self ? $exception->context() : [
            'failure_class' => $exception::class,
            'failure_file' => $exception->getFile(),
            'failure_line' => $exception->getLine(),
            'failure_trace' => array_map(
                static fn (array $frame): array => array_intersect_key(
                    $frame,
                    array_flip(['file', 'line', 'class', 'function'])
                ),
                $exception->getTrace(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->diagnostics;
    }
}
