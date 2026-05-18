<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use JsonSerializable;

/**
 * A block (node) in the Merkle Search Tree that makes up a repo.
 * Maps to the `repo_block` table in the actor-store schema.
 *
 * Lives inside an ActorStore scoped to a specific DID, so no `did` field here.
 * `content` is raw bytes (CBOR-encoded); modelled as a plain string.
 */
class RepoBlock implements JsonSerializable
{
    public function __construct(
        private readonly string $cid,
        private readonly string $repoRev,
        private readonly int $size,
        private readonly string $content,
    ) {
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getRepoRev(): string
    {
        return $this->repoRev;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'cid'     => $this->cid,
            'repoRev' => $this->repoRev,
            'size'    => $this->size,
        ];
    }
}
