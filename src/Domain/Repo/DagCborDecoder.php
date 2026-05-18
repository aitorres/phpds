<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Decodes dag-cbor bytes back into a PHP value tree.
 *
 * CBOR types -> PHP types:
 *   major 0 (uint)            -> int
 *   major 1 (negative int)    -> int
 *   major 2 (byte string)     -> CborBytes
 *   major 3 (text string)     -> string
 *   major 4 (array)           -> list<mixed>
 *   major 5 (map)             -> array<string, mixed>
 *   tag(42) (CID link)        -> CidLink
 *   0xf4 false                -> false
 *   0xf5 true                 -> true
 *   0xf6 null                 -> null
 *   0xfb (double)             -> float
 */
interface DagCborDecoder
{
    /**
     * @return mixed null|bool|int|float|string|CborBytes|CidLink|array<mixed>
     */
    public function decode(string $bytes): mixed;

    /**
     * Decode all concatenated dag-cbor values from $bytes.
     *
     * @return list<mixed>
     */
    public function decodeAll(string $bytes): array;
}
