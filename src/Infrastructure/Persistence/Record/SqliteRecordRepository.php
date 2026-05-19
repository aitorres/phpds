<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\Record;
use App\Domain\Record\RecordNotFoundException;
use App\Domain\Record\RecordRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteRecordRepository implements RecordRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByUri(string $uri): Record
    {
        $row = $this->db->fetchOne('SELECT * FROM record WHERE uri = ?', [$uri]);

        if ($row === null) {
            throw new RecordNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return Record[]
     */
    public function findByCollection(string $collection): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM record WHERE collection = ? ORDER BY uri',
            [$collection]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return Record[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM record ORDER BY uri');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(Record $record): void
    {
        $this->db->execute(
            'INSERT INTO record (uri, cid, collection, rkey, repo_rev, indexed_at, takedown_ref)
             VALUES (:uri, :cid, :collection, :rkey, :repo_rev, :indexed_at, :takedown_ref)
             ON CONFLICT(uri) DO UPDATE SET
                cid = excluded.cid,
                collection = excluded.collection,
                rkey = excluded.rkey,
                repo_rev = excluded.repo_rev,
                indexed_at = excluded.indexed_at,
                takedown_ref = excluded.takedown_ref',
            [
                'uri'          => $record->getUri(),
                'cid'          => $record->getCid(),
                'collection'   => $record->getCollection(),
                'rkey'         => $record->getRkey(),
                'repo_rev'     => $record->getRepoRev(),
                'indexed_at'   => $record->getIndexedAt()->format(DATE_ATOM),
                'takedown_ref' => $record->getTakedownRef(),
            ]
        );
    }

    public function deleteByUri(string $uri): void
    {
        $this->db->execute('DELETE FROM record WHERE uri = ?', [$uri]);
    }

    public function deleteAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM record');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Record
    {
        return new Record(
            uri: Row::str($row, 'uri'),
            cid: Row::str($row, 'cid'),
            collection: Row::str($row, 'collection'),
            rkey: Row::str($row, 'rkey'),
            repoRev: Row::str($row, 'repo_rev'),
            indexedAt: new DateTimeImmutable(Row::str($row, 'indexed_at')),
            takedownRef: Row::nstr($row, 'takedown_ref'),
        );
    }
}
