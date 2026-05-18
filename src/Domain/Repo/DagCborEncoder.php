<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Encodes a PHP value tree to dag-cbor bytes.
 *
 * Supported PHP types -> CBOR major types:
 *   null          -> 0xf6
 *   bool          -> 0xf4 / 0xf5
 *   int           -> major 0 (unsigned) or major 1 (negative)
 *   float         -> major 7 double (0xfb), always 64-bit IEEE 754
 *   string        -> major 3 (UTF-8 text)
 *   CborBytes     -> major 2 (byte string)
 *   CidLink       -> tag(42) bstr("\x00" + raw_cid)
 *   array (list)  -> major 4 (array)
 *   array (map)   -> major 5 (map), keys sorted length-first then lexicographic
 */
interface DagCborEncoder
{
    /**
     * @param mixed $value null|bool|int|float|string|CborBytes|CidLink|array<mixed>
     */
    public function encode(mixed $value): string;
}
