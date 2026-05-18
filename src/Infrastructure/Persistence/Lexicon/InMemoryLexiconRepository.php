<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Lexicon;

use App\Domain\Lexicon\LexiconEntry;
use App\Domain\Lexicon\LexiconEntryNotFoundException;
use App\Domain\Lexicon\LexiconRepository;

class InMemoryLexiconRepository implements LexiconRepository
{
    /** @var array<string, LexiconEntry> keyed by nsid */
    private array $entries = [];

    /**
     * @param LexiconEntry[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $entry) {
            $this->entries[$entry->getNsid()] = $entry;
        }
    }

    public function findByNsid(string $nsid): LexiconEntry
    {
        if (!isset($this->entries[$nsid])) {
            throw new LexiconEntryNotFoundException();
        }

        return $this->entries[$nsid];
    }

    /**
     * @return LexiconEntry[]
     */
    public function findAll(): array
    {
        return array_values($this->entries);
    }

    public function save(LexiconEntry $entry): void
    {
        $this->entries[$entry->getNsid()] = $entry;
    }

    public function deleteByNsid(string $nsid): void
    {
        unset($this->entries[$nsid]);
    }
}
