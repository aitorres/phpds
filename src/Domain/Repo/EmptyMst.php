<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Minimal MST (Merkle Search Tree) helpers needed for account creation.
 *
 * Useful to generate a freshly-created account's empty MST node.
 *
 * The empty MST is the CBOR-encoded map:
 *   {
 *     "l": null,
 *     "e": []
 *   }
 *
 *  encoded as DAG-CBOR. Its CID (CIDv1 + dag-cbor + sha2-256) is the
 * `data` field of the genesis commit.
 *
 * `l` is the left child link.
 * `e` is the list of entries in this MST node.
 */
final class EmptyMst
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function toMap(): array
    {
        return [
            'l' => null,
            'e' => [],
        ];
    }

    /**
     * Encode and return [cborBytes, cidString].
     *
     * @return array{0: string, 1: string}
     */
    public static function encode(DagCborEncoder $encoder): array
    {
        $bytes = $encoder->encode(self::toMap());
        $cid   = CidUtil::computeForDagCbor($bytes);
        return [$bytes, $cid];
    }
}
