<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Deserialises a CARv1 binary stream into root CIDs and block map.
 */
interface CarReader
{
    /**
     * @return array{roots: string[], blocks: array<string, string>}
     *   roots  — CID strings of the root blocks declared in the header
     *   blocks — CID string -> raw dag-cbor bytes
     */
    public function read(string $carBytes): array;
}
