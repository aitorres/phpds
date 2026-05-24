<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use InvalidArgumentException;

final class MstKey
{
    private const VALID_CHARS_PATTERN = '/^[a-zA-Z0-9_~\-:.]*$/';

    private function __construct()
    {
    }

    public static function isValid(string $key): bool
    {
        $parts = explode('/', $key);

        return strlen($key) <= 1024
            && count($parts) === 2
            && $parts[0] !== ''
            && $parts[1] !== ''
            && self::hasValidChars($parts[0])
            && self::hasValidChars($parts[1]);
    }

    public static function ensureValid(string $key): void
    {
        if (!self::isValid($key)) {
            throw new InvalidArgumentException("Not a valid MST key: {$key}");
        }
    }

    public static function countSharedPrefix(string $left, string $right): int
    {
        $limit = min(strlen($left), strlen($right));

        for ($index = 0; $index < $limit; $index++) {
            if ($left[$index] !== $right[$index]) {
                return $index;
            }
        }

        return $limit;
    }

    public static function leadingZeros(string $key): int
    {
        $hash = hash('sha256', $key, true);
        $leadingZeros = 0;

        for ($index = 0, $length = strlen($hash); $index < $length; $index++) {
            $byte = ord($hash[$index]);

            if ($byte < 64) {
                $leadingZeros++;
            }

            if ($byte < 16) {
                $leadingZeros++;
            }

            if ($byte < 4) {
                $leadingZeros++;
            }

            if ($byte === 0) {
                $leadingZeros++;
                continue;
            }

            break;
        }

        return $leadingZeros;
    }

    private static function hasValidChars(string $value): bool
    {
        return preg_match(self::VALID_CHARS_PATTERN, $value) === 1;
    }
}
