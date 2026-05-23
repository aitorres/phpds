<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use App\Domain\Common\StringNormalizer;
use App\Domain\Pds\Atproto\Sync\RepoView;
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
        $this->handle = StringNormalizer::normalizeHandle($handle);
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
     * Derive the lex `status` value for this actor's repo.
     *
     * Returns null when the repo is active, otherwise the matching
     * non-active status string (e.g. "takendown", "deactivated").
     * Takedown takes precedence over deactivation.
     */
    public function getRepoStatus(): ?string
    {
        if ($this->takedownRef !== null) {
            return RepoView::STATUS_TAKENDOWN;
        }

        if ($this->deactivatedAt !== null) {
            return RepoView::STATUS_DEACTIVATED;
        }

        return null;
    }

    public function isRepoActive(): bool
    {
        return $this->getRepoStatus() === null;
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
