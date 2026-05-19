<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\Backlink;
use App\Domain\Record\BacklinkRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;

class SqliteBacklinkRepository implements BacklinkRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return Backlink[]
     */
    public function findByUri(string $uri): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM backlink WHERE uri = ? ORDER BY path, link_to',
            [$uri]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return Backlink[]
     */
    public function findByLinkTo(string $linkTo): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM backlink WHERE link_to = ? ORDER BY uri, path',
            [$linkTo]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(Backlink $backlink): void
    {
        $this->db->execute(
            'INSERT OR IGNORE INTO backlink (uri, path, link_to) VALUES (?, ?, ?)',
            [
                $backlink->getUri(),
                $backlink->getPath(),
                $backlink->getLinkTo(),
            ]
        );
    }

    public function deleteByUri(string $uri): void
    {
        $this->db->execute('DELETE FROM backlink WHERE uri = ?', [$uri]);
    }

    public function deleteAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM backlink');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Backlink
    {
        return new Backlink(
            uri: Row::str($row, 'uri'),
            path: Row::str($row, 'path'),
            linkTo: Row::str($row, 'link_to'),
        );
    }
}
