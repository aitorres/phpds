<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\Record;
use App\Domain\Record\RecordNotFoundException;
use App\Domain\Record\RecordRepository;

class InMemoryRecordRepository implements RecordRepository
{
    /** @var array<string, Record> keyed by uri */
    private array $records = [];

    /**
     * @param Record[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $record) {
            $this->records[$record->getUri()] = $record;
        }
    }

    public function findByUri(string $uri): Record
    {
        if (!isset($this->records[$uri])) {
            throw new RecordNotFoundException();
        }

        return $this->records[$uri];
    }

    /**
     * @return Record[]
     */
    public function findByCollection(string $collection): array
    {
        return array_values(
            array_filter(
                $this->records,
                fn(Record $r) => $r->getCollection() === $collection,
            )
        );
    }

    /**
     * @return Record[]
     */
    public function findAll(): array
    {
        return array_values($this->records);
    }

    public function save(Record $record): void
    {
        $this->records[$record->getUri()] = $record;
    }

    public function deleteByUri(string $uri): void
    {
        unset($this->records[$uri]);
    }

    public function deleteAll(): void
    {
        $this->records = [];
    }
}
