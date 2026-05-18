<?php

declare(strict_types=1);

namespace App\Domain\Blob;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Blob metadata row. Binary content is managed separately by BlobStore.
 * Maps to the `blob` table in the actor-store schema.
 *
 * Scoped to an ActorStore with a specific DID, no `did` field here.
 */
class Blob implements JsonSerializable
{
    public function __construct(
        private readonly string $cid,
        private readonly string $mimeType,
        private readonly int $size,
        private readonly ?string $tempKey,
        private readonly DateTimeImmutable $createdAt,
        private readonly ?string $takedownRef = null,
    ) {
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getTempKey(): ?string
    {
        return $this->tempKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTakedownRef(): ?string
    {
        return $this->takedownRef;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'cid'         => $this->cid,
            'mimeType'    => $this->mimeType,
            'size'        => $this->size,
            'tempKey'     => $this->tempKey,
            'createdAt'   => $this->createdAt->format(DATE_ATOM),
            'takedownRef' => $this->takedownRef,
        ];
    }
}
