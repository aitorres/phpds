<?php

declare(strict_types=1);

namespace App\Domain\Repo;

/**
 * The unsigned form of an atproto repo commit (version 3).
 *
 *   {
 *     did:     "did:plc:..."
 *     version: 3
 *     data:    <CidLink to MST root>
 *     rev:     "<TID>"
 *     prev:    null
 *   }
 *
 * Encoded to DAG-CBOR for the signing step that produces a {@see Commit}.
 */
final class UnsignedCommit
{
    public const VERSION = 3;

    public function __construct(
        private readonly string $did,
        private readonly CidLink $data,
        private readonly string $rev,
        private readonly ?CidLink $prev = null,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getData(): CidLink
    {
        return $this->data;
    }

    public function getRev(): string
    {
        return $this->rev;
    }

    public function getPrev(): ?CidLink
    {
        return $this->prev;
    }

    /**
     * @return array<string, mixed>
     */
    public function toMap(): array
    {
        return [
            'did'     => $this->did,
            'version' => self::VERSION,
            'data'    => $this->data,
            'rev'     => $this->rev,
            'prev'    => $this->prev,
        ];
    }
}
