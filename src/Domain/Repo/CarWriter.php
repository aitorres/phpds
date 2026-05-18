<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Serialises a set of repo blocks as a CARv1 binary stream.
 *
 * CARv1 layout:
 *   <uvarint: header-cbor-length> <header-cbor>
 *   (<uvarint: cid-bytes+block-data-length> <raw-cid-bytes> <block-data>)*
 *
 * The header is dag-cbor: {version: 1, roots: [<CidLink>, ...]}.
 */
interface CarWriter
{
    /**
     * @param string[]             $rootCids  CID strings of the root blocks
     * @param array<string,string> $blocks    CID string -> raw dag-cbor bytes
     */
    public function write(array $rootCids, array $blocks): string;
}
