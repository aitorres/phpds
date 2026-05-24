<?php

declare(strict_types=1);

namespace App\Domain\Did;

/**
 * Resolves a DID to its DID document.
 *
 * Returns the parsed document array, or null when the DID cannot be resolved
 * (unknown method, network error, document not found, etc.).
 */
interface DidResolver
{
    /**
     * @return array<string, mixed>|null
     */
    public function resolve(string $did): ?array;
}
