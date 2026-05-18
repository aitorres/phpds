<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\Backlink;
use App\Domain\Record\BacklinkRepository;

class InMemoryBacklinkRepository implements BacklinkRepository
{
    /** @var Backlink[] */
    private array $entries = [];

    /**
     * @param Backlink[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        $this->entries = $seeds;
    }

    /**
     * @return Backlink[]
     */
    public function findByUri(string $uri): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(Backlink $b) => $b->getUri() === $uri,
            )
        );
    }

    /**
     * @return Backlink[]
     */
    public function findByLinkTo(string $linkTo): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(Backlink $b) => $b->getLinkTo() === $linkTo,
            )
        );
    }

    public function save(Backlink $backlink): void
    {
        foreach ($this->entries as $existing) {
            if (
                $existing->getUri() === $backlink->getUri()
                && $existing->getPath() === $backlink->getPath()
                && $existing->getLinkTo() === $backlink->getLinkTo()
            ) {
                return;
            }
        }
        $this->entries[] = $backlink;
    }

    public function deleteByUri(string $uri): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(Backlink $b) => $b->getUri() !== $uri,
            )
        );
    }

    public function deleteAll(): void
    {
        $this->entries = [];
    }
}
