<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use DateTimeImmutable;
use JsonSerializable;

class RepoRoot implements JsonSerializable
{
    public function __construct(
        private readonly string $did,
        private readonly string $cid,
        private readonly string $rev,
        private readonly DateTimeImmutable $indexedAt,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getRev(): string
    {
        return $this->rev;
    }

    public function getIndexedAt(): DateTimeImmutable
    {
        return $this->indexedAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did'       => $this->did,
            'cid'       => $this->cid,
            'rev'       => $this->rev,
            'indexedAt' => $this->indexedAt->format(DATE_ATOM),
        ];
    }
}
