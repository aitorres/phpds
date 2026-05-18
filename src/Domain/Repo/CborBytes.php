<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * Wraps a raw byte string so the dag-cbor encoder knows to emit it as a
 * CBOR major-type-2 byte string rather than a UTF-8 text string.
 */
final class CborBytes
{
    public function __construct(private readonly string $bytes)
    {
    }

    public function getBytes(): string
    {
        return $this->bytes;
    }
}
