<?php

declare(strict_types=1);

namespace App\Domain\Record;

interface RecordRepository
{
    /**
     * @throws RecordNotFoundException
     */
    public function findByUri(string $uri): Record;

    /**
     * @return Record[]
     */
    public function findByCollection(string $collection): array;

    /**
     * @return Record[]
     */
    public function findAll(): array;

    public function save(Record $record): void;

    public function deleteByUri(string $uri): void;

    public function deleteAll(): void;
}
