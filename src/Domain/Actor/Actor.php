<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use DateTimeImmutable;
use JsonSerializable;

class Actor implements JsonSerializable
{
    private string $did;

    private ?string $handle;

    private DateTimeImmutable $createdAt;

    private ?string $takedownRef;

    private ?DateTimeImmutable $deactivatedAt;

    private ?DateTimeImmutable $deleteAfter;

    public function __construct(
        string $did,
        ?string $handle,
        DateTimeImmutable $createdAt,
        ?string $takedownRef = null,
        ?DateTimeImmutable $deactivatedAt = null,
        ?DateTimeImmutable $deleteAfter = null
    ) {
        $this->did = $did;
        $this->handle = $handle === null ? null : strtolower(trim($handle));
        $this->createdAt = $createdAt;
        $this->takedownRef = $takedownRef;
        $this->deactivatedAt = $deactivatedAt;
        $this->deleteAfter = $deleteAfter;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHandle(): ?string
    {
        return $this->handle;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getTakedownRef(): ?string
    {
        return $this->takedownRef;
    }

    public function getDeactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function getDeleteAfter(): ?DateTimeImmutable
    {
        return $this->deleteAfter;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did' => $this->did,
            'handle' => $this->handle,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'takedownRef' => $this->takedownRef,
            'deactivatedAt' => $this->deactivatedAt?->format(DATE_ATOM),
            'deleteAfter' => $this->deleteAfter?->format(DATE_ATOM),
        ];
    }
}
