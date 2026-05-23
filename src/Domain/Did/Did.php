<?php

declare(strict_types=1);

namespace App\Domain\Did;

/**
 * DID syntax helpers.
 *
 * @see https://www.w3.org/TR/did-core/#did-syntax
 */
final class Did
{
    public const PREFIX = 'did:';

    private function __construct()
    {
    }

    /**
     * Returns true when $did has the structural form `did:<method>:<id>`
     * with non-empty method and identifier parts.
     */
    public static function isValid(string $did): bool
    {
        if (!str_starts_with($did, self::PREFIX)) {
            return false;
        }

        $parts = explode(':', $did, 3);
        if (count($parts) !== 3) {
            return false;
        }

        return $parts[1] !== '' && $parts[2] !== '';
    }
}
