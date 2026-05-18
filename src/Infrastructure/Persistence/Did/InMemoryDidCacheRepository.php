<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Did;

use App\Domain\Did\DidCacheRepository;
use App\Domain\Did\DidDocEntry;
use App\Domain\Did\DidDocEntryNotFoundException;
use DateTimeImmutable;

class InMemoryDidCacheRepository implements DidCacheRepository
{
    /** @var array<string, DidDocEntry> keyed by did */
    private array $cache = [];

    /**
     * @param DidDocEntry[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $entry) {
            $this->cache[$entry->getDid()] = $entry;
        }
    }

    public function get(string $did): DidDocEntry
    {
        if (!isset($this->cache[$did])) {
            throw new DidDocEntryNotFoundException();
        }

        return $this->cache[$did];
    }

    /**
     * @param array<string, mixed> $doc
     */
    public function set(string $did, array $doc): void
    {
        $this->cache[$did] = new DidDocEntry(
            did: $did,
            doc: $doc,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function has(string $did): bool
    {
        return isset($this->cache[$did]);
    }

    public function clear(string $did): void
    {
        unset($this->cache[$did]);
    }

    public function clearAll(): void
    {
        $this->cache = [];
    }
}
