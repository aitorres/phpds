<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Did;

use App\Domain\Did\DidCacheRepository;
use App\Domain\Did\DidDocEntry;
use App\Domain\Did\DidDocEntryNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteDidCacheRepository implements DidCacheRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function get(string $did): DidDocEntry
    {
        $row = $this->db->fetchOne('SELECT * FROM did_doc WHERE did = ?', [$did]);

        if ($row === null) {
            throw new DidDocEntryNotFoundException();
        }

        $doc = json_decode(Row::str($row, 'doc_json'), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($doc));
        /** @var array<string, mixed> $doc */

        return new DidDocEntry(
            did: Row::str($row, 'did'),
            doc: $doc,
            updatedAt: new DateTimeImmutable(Row::str($row, 'updated_at')),
        );
    }

    /**
     * @param array<string, mixed> $doc
     */
    public function set(string $did, array $doc): void
    {
        $this->db->execute(
            'INSERT INTO did_doc (did, doc_json, updated_at)
             VALUES (?, ?, ?)
             ON CONFLICT(did) DO UPDATE SET
                doc_json = excluded.doc_json,
                updated_at = excluded.updated_at',
            [
                $did,
                json_encode($doc, JSON_THROW_ON_ERROR),
                (new DateTimeImmutable())->format(DATE_ATOM),
            ]
        );
    }

    public function has(string $did): bool
    {
        return $this->db->fetchOne('SELECT 1 FROM did_doc WHERE did = ?', [$did]) !== null;
    }

    public function clear(string $did): void
    {
        $this->db->execute('DELETE FROM did_doc WHERE did = ?', [$did]);
    }

    public function clearAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM did_doc');
    }
}
