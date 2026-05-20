<?php

declare(strict_types=1);

namespace App\Domain\Common;

/**
 * Small helpers for canonicalizing user-provided identifiers
 * before storage or comparison.
 */
final class StringNormalizer
{
    /**
     * Normalize a handle by trimming surrounding whitespace and lowercasing.
     * Returns null when the input is null.
     */
    public static function normalizeHandle(?string $handle): ?string
    {
        return $handle === null ? null : strtolower(trim($handle));
    }

    /**
     * Normalize an email by trimming surrounding whitespace and lowercasing.
     */
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
