<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Runtime type checks for values crossing request, cache and provider boundaries. */
final class Input
{
    /** @return array<string, mixed> */
    public static function object(mixed $value, string $field = 'input'): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages([$field => 'An object is required.']);
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw ValidationException::withMessages([$field => 'Object keys must be strings.']);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    /** @return array<array-key, array<string, mixed>> */
    public static function objects(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages([$field => 'An array is required.']);
        }
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = self::object($item, $field);
        }

        return $result;
    }

    public static function string(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'A string is required.']);
        }

        return $value;
    }

    public static function integer(mixed $value, string $field): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if (! is_int($integer)) {
            throw ValidationException::withMessages([$field => 'An integer is required.']);
        }

        return $integer;
    }
}
