<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use App\Domain\Crypto\Keypair;

/**
 * A signed atproto repo commit (version 3).
 *
 *   {
 *     did:     "did:plc:..."
 *     version: 3
 *     data:    <CidLink to top of MST>
 *     rev:     "<TID>"
 *     prev:    nullable, <CidLink to previous commit>
 *     sig:     <CborBytes: 64-byte secp256k1 signature>
 *   }
 *
 * @see https://atproto.com/specs/repository
 */
final class Commit
{
    public const VERSION = 3;

    public static function fromUnsigned(
        UnsignedCommit $unsigned,
        Keypair $signer,
        DagCborEncoder $cborEncoder,
    ): self {
        $unsignedBytes = $cborEncoder->encode($unsigned->toMap());
        $signature     = $signer->sign($unsignedBytes);

        return new self(
            $unsigned->getDid(),
            $unsigned->getData(),
            $unsigned->getRev(),
            $unsigned->getPrev(),
            $signature,
        );
    }

    public function __construct(
        private readonly string $did,
        private readonly CidLink $data,
        private readonly string $rev,
        private readonly ?CidLink $prev,
        private readonly string $signature,
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

    public function getSignature(): string
    {
        return $this->signature;
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
            'sig'     => new CborBytes($this->signature),
        ];
    }
}
