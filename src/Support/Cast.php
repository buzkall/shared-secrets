<?php

namespace Arzcode\SharedSecrets\Support;

/**
 * Narrows the mixed values coming out of form state.
 */
class Cast
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string)$value : $default;
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string)$value : null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function key(mixed $value): int|string|null
    {
        return is_int($value) || (is_string($value) && $value !== '') ? $value : null;
    }
}
