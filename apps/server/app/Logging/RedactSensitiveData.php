<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

class RedactSensitiveData implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->containsException($record->context) || $this->containsException($record->extra)
                ? 'Application exception reported.' : $this->text($record->message),
            context: $this->sanitize($record->context),
            extra: $this->sanitize($record->extra),
        );
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function sanitize(array $values, int $depth = 0): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (
                preg_match(
                    '/password|secret|token|cookie|authorization|api[_-]?key|credential|recovery[_-]?codes/i',
                    (string) $key
                )
            ) {
                $result[$key] = '[REDACTED]';
            } elseif ($value instanceof Throwable) {
                $result[$key] = [
                    'class' => $value::class,
                    'message' => '[REDACTED exception message]',
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                    'trace' => array_map(
                        fn (array $frame): array => array_intersect_key(
                            $frame,
                            array_flip(['file', 'line', 'class', 'function'])
                        ),
                        $value->getTrace()
                    )
                ];
            } elseif (is_array($value)) {
                $result[$key] = $depth >= 10 ? '[TRUNCATED]' : $this->sanitize($value, $depth + 1);
            } elseif (is_object($value)) {
                $result[$key] = '[OBJECT ' . $value::class . ']';
            } else {
                $result[$key] = is_string($value) ? $this->text($value) : $value;
            }
        }

        return $result;
    }

    /** @param array<array-key, mixed> $values */
    private function containsException(array $values, int $depth = 0): bool
    {
        foreach ($values as $value) {
            if (
                $value instanceof Throwable || (is_array($value) && $depth < 10
                && $this->containsException($value, $depth + 1))
            ) {
                return true;
            }
        }

        return false;
    }

    private function text(string $value): string
    {
        if (str_contains($value, '(Connection: ') && str_contains($value, ', SQL: ')) {
            return '[REDACTED database exception message]';
        }
        $value = preg_replace('/\bBearer\s+[^\s,"\x27]+/i', 'Bearer [REDACTED]', $value) ?? '[REDACTED]';
        $value = preg_replace(
            '/\b(?:sk-[a-zA-Z0-9_-]{8,}|AIza[a-zA-Z0-9_-]{20,})\b/',
            '[REDACTED]',
            $value
        ) ?? '[REDACTED]';

        return preg_replace(
            '/((?:api[_-]?key|password|secret|access[_-]?token|refresh[_-]?token)'
                . '["\x27]?\s*[:=]\s*)(?:"[^"\r\n]*"|\x27[^\x27\r\n]*\x27|[^\s,;&]+)/i',
            '$1[REDACTED]',
            $value
        ) ?? '[REDACTED]';
    }
}
