<?php

declare(strict_types=1);

namespace App\Domain\Lexicon;

interface LexiconRepository
{
    /**
     * @throws LexiconEntryNotFoundException
     */
    public function findByNsid(string $nsid): LexiconEntry;

    /**
     * @return LexiconEntry[]
     */
    public function findAll(): array;

    public function save(LexiconEntry $entry): void;

    public function deleteByNsid(string $nsid): void;
}
