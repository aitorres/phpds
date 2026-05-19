<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootNotFoundException;
use App\Domain\Repo\RepoRootRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteRepoRootRepository implements RepoRootRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByDid(string $did): RepoRoot
    {
        $row = $this->db->fetchOne('SELECT * FROM repo_root WHERE did = ?', [$did]);

        if ($row === null) {
            throw new RepoRootNotFoundException();
        }

        return new RepoRoot(
            did: Row::str($row, 'did'),
            cid: Row::str($row, 'cid'),
            rev: Row::str($row, 'rev'),
            indexedAt: new DateTimeImmutable(Row::str($row, 'indexed_at')),
        );
    }

    public function upsert(RepoRoot $repoRoot): void
    {
        $this->db->execute(
            'INSERT INTO repo_root (did, cid, rev, indexed_at)
             VALUES (:did, :cid, :rev, :indexed_at)
             ON CONFLICT(did) DO UPDATE SET
                cid = excluded.cid,
                rev = excluded.rev,
                indexed_at = excluded.indexed_at',
            [
                'did'        => $repoRoot->getDid(),
                'cid'        => $repoRoot->getCid(),
                'rev'        => $repoRoot->getRev(),
                'indexed_at' => $repoRoot->getIndexedAt()->format(DATE_ATOM),
            ]
        );
    }

    public function deleteByDid(string $did): void
    {
        $this->db->execute('DELETE FROM repo_root WHERE did = ?', [$did]);
    }
}
