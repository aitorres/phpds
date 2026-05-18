<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Represents a CID link inside a dag-cbor document (CBOR tag 42).
 *
 * When the encoder encounters a CidLink it emits:
 *   tag(42) bstr("\x00" + raw_cid_bytes)
 * When the decoder encounters tag 42 it returns a CidLink.
 */
final class CidLink
{
    public function __construct(private readonly string $cid)
    {
    }

    public function getCid(): string
    {
        return $this->cid;
    }
}
