<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Blob;

use App\Domain\Blob\Blob;
use App\Domain\Blob\BlobNotFoundException;
use App\Domain\Blob\BlobRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteBlobRepository implements BlobRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByCid(string $cid): Blob
    {
        $row = $this->db->fetchOne('SELECT * FROM blob WHERE cid = ?', [$cid]);

        if ($row === null) {
            throw new BlobNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return Blob[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM blob ORDER BY cid');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return Blob[]
     */
    public function findTemporary(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM blob WHERE temp_key IS NOT NULL ORDER BY cid'
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(Blob $blob): void
    {
        $this->db->execute(
            'INSERT INTO blob (cid, mime_type, size, temp_key, created_at, takedown_ref)
             VALUES (:cid, :mime_type, :size, :temp_key, :created_at, :takedown_ref)
             ON CONFLICT(cid) DO UPDATE SET
                mime_type = excluded.mime_type,
                size = excluded.size,
                temp_key = excluded.temp_key,
                created_at = excluded.created_at,
                takedown_ref = excluded.takedown_ref',
            [
                'cid'          => $blob->getCid(),
                'mime_type'    => $blob->getMimeType(),
                'size'         => $blob->getSize(),
                'temp_key'     => $blob->getTempKey(),
                'created_at'   => $blob->getCreatedAt()->format(DATE_ATOM),
                'takedown_ref' => $blob->getTakedownRef(),
            ]
        );
    }

    public function deleteByCid(string $cid): void
    {
        $this->db->execute('DELETE FROM blob WHERE cid = ?', [$cid]);
    }

    public function deleteAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM blob');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Blob
    {
        return new Blob(
            cid: Row::str($row, 'cid'),
            mimeType: Row::str($row, 'mime_type'),
            size: Row::int($row, 'size'),
            tempKey: Row::nstr($row, 'temp_key'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            takedownRef: Row::nstr($row, 'takedown_ref'),
        );
    }
}
