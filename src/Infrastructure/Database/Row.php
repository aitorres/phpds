<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

/**
 * Type-safe accessors for values fetched from a PDO row.
 *
 * SQLite columns are returned as mixed by PHPStan, but our schemas guarantee
 * specific column types.
 *
 * We narrow values with `assert` for type guarding, and throwing at runtime
 * on unexpected shapes/types.
 */
final class Row
{
    /** @param array<string, mixed> $row */
    public static function str(array $row, string $key): string
    {
        $v = $row[$key] ?? null;
        assert(is_string($v));
        return $v;
    }

    /** @param array<string, mixed> $row */
    public static function nstr(array $row, string $key): ?string
    {
        $v = $row[$key] ?? null;
        assert($v === null || is_string($v));
        return $v;
    }

    /** @param array<string, mixed> $row */
    public static function int(array $row, string $key): int
    {
        $v = $row[$key] ?? null;
        assert(is_int($v) || (is_string($v) && $v !== '' && (string) (int) $v === $v));
        return (int) $v;
    }

    /** @param array<string, mixed> $row */
    public static function nint(array $row, string $key): ?int
    {
        $v = $row[$key] ?? null;
        if ($v === null) {
            return null;
        }
        assert(is_int($v) || is_string($v));
        return (int) $v;
    }

    /** @param array<string, mixed> $row */
    public static function bool(array $row, string $key): bool
    {
        $v = $row[$key] ?? null;
        assert(is_int($v) || is_bool($v) || is_string($v));
        return (bool) (int) $v;
    }
}
