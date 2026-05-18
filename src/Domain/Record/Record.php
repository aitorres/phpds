<?php

declare(strict_types=1);

namespace App\Domain\Record;

use DateTimeImmutable;
use JsonSerializable;

/**
 * An indexed AT Protocol record (a lexicon-typed item in a repo collection).
 * Maps to the `record` table in the reference TS actor-store schema.
 *
 * Lives inside an ActorStore scoped to a specific DID, so no `did` field here.
 */
class Record implements JsonSerializable
{
    public function __construct(
        private readonly string $uri,
        private readonly string $cid,
        private readonly string $collection,
        private readonly string $rkey,
        private readonly string $repoRev,
        private readonly DateTimeImmutable $indexedAt,
        private readonly ?string $takedownRef = null,
    ) {
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getCollection(): string
    {
        return $this->collection;
    }

    public function getRkey(): string
    {
        return $this->rkey;
    }

    public function getRepoRev(): string
    {
        return $this->repoRev;
    }

    public function getIndexedAt(): DateTimeImmutable
    {
        return $this->indexedAt;
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
            'uri'         => $this->uri,
            'cid'         => $this->cid,
            'collection'  => $this->collection,
            'rkey'        => $this->rkey,
            'repoRev'     => $this->repoRev,
            'indexedAt'   => $this->indexedAt->format(DATE_ATOM),
            'takedownRef' => $this->takedownRef,
        ];
    }
}
