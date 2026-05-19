<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockNotFoundException;
use App\Domain\Repo\RepoBlockRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use PDO;

class SqliteRepoBlockRepository implements RepoBlockRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByCid(string $cid): RepoBlock
    {
        $row = $this->db->fetchOne('SELECT * FROM repo_block WHERE cid = ?', [$cid]);

        if ($row === null) {
            throw new RepoBlockNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return RepoBlock[]
     */
    public function findByRepoRev(string $repoRev): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM repo_block WHERE repo_rev = ? ORDER BY cid',
            [$repoRev]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return RepoBlock[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM repo_block ORDER BY cid');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(RepoBlock $block): void
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO repo_block (cid, repo_rev, size, content)
             VALUES (:cid, :repo_rev, :size, :content)
             ON CONFLICT(cid) DO UPDATE SET
                repo_rev = excluded.repo_rev,
                size = excluded.size,
                content = excluded.content'
        );
        assert($stmt !== false);
        $stmt->bindValue(':cid', $block->getCid());
        $stmt->bindValue(':repo_rev', $block->getRepoRev());
        $stmt->bindValue(':size', $block->getSize(), PDO::PARAM_INT);
        $stmt->bindValue(':content', $block->getContent(), PDO::PARAM_LOB);
        $stmt->execute();
    }

    public function deleteByCid(string $cid): void
    {
        $this->db->execute('DELETE FROM repo_block WHERE cid = ?', [$cid]);
    }

    public function deleteAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM repo_block');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RepoBlock
    {
        // Some PDO/sqlite versions return BLOB columns as resources; normalize.
        $content = $row['content'] ?? null;
        if (is_resource($content)) {
            $content = stream_get_contents($content);
            if ($content === false) {
                $content = '';
            }
        }
        assert(is_string($content));

        return new RepoBlock(
            cid: Row::str($row, 'cid'),
            repoRev: Row::str($row, 'repo_rev'),
            size: Row::int($row, 'size'),
            content: $content,
        );
    }
}
