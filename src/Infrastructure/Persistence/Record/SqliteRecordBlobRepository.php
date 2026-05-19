<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\RecordBlob;
use App\Domain\Record\RecordBlobRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;

class SqliteRecordBlobRepository implements RecordBlobRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return RecordBlob[]
     */
    public function findByBlobCid(string $blobCid): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM record_blob WHERE blob_cid = ? ORDER BY record_uri',
            [$blobCid]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return RecordBlob[]
     */
    public function findByRecordUri(string $recordUri): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM record_blob WHERE record_uri = ? ORDER BY blob_cid',
            [$recordUri]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(RecordBlob $recordBlob): void
    {
        $this->db->execute(
            'INSERT OR IGNORE INTO record_blob (blob_cid, record_uri) VALUES (?, ?)',
            [$recordBlob->getBlobCid(), $recordBlob->getRecordUri()]
        );
    }

    public function deleteByRecordUri(string $recordUri): void
    {
        $this->db->execute('DELETE FROM record_blob WHERE record_uri = ?', [$recordUri]);
    }

    public function deleteByBlobCid(string $blobCid): void
    {
        $this->db->execute('DELETE FROM record_blob WHERE blob_cid = ?', [$blobCid]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RecordBlob
    {
        return new RecordBlob(
            blobCid: Row::str($row, 'blob_cid'),
            recordUri: Row::str($row, 'record_uri'),
        );
    }
}
