<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

use DateTimeImmutable;

/**
 * A signing keypair persisted in the per-actor store, identified by curve.
 */
final class StoredSigningKey
{
    public function __construct(
        private readonly string $curve,
        private readonly string $privateKey,
        private readonly string $didKey,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function getCurve(): string
    {
        return $this->curve;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getDidKey(): string
    {
        return $this->didKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
