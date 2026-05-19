<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sequencer;

use App\Domain\Sequencer\RepoSeqEvent;
use App\Domain\Sequencer\SequencerRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;
use PDO;

class SqliteSequencerRepository implements SequencerRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function append(string $did, string $eventType, string $event): int
    {
        $pdo  = $this->db->pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO repo_seq (did, event_type, event, sequenced_at, invalidated)
             VALUES (?, ?, ?, ?, 0)'
        );
        assert($stmt !== false);
        $stmt->bindValue(1, $did);
        $stmt->bindValue(2, $eventType);
        $stmt->bindValue(3, $event, PDO::PARAM_LOB);
        $stmt->bindValue(4, (new DateTimeImmutable())->format(DATE_ATOM));
        $stmt->execute();

        return (int) $pdo->lastInsertId();
    }

    public function latestSeq(): ?int
    {
        $query = $this->db->pdo()->query('SELECT MAX(seq) FROM repo_seq');
        assert($query !== false);
        $result = $query->fetchColumn();

        if ($result === false || $result === null) {
            return null;
        }

        return (int) $result;
    }

    /**
     * @return RepoSeqEvent[]
     */
    public function readRange(int $afterSeq, int $limit = 500): array
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT * FROM repo_seq WHERE seq > ? ORDER BY seq ASC LIMIT ?'
        );
        assert($stmt !== false);
        $stmt->bindValue(1, $afterSeq, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $events = [];
        foreach ($rows as $row) {
            $events[] = new RepoSeqEvent(
                seq: Row::int($row, 'seq'),
                did: Row::str($row, 'did'),
                eventType: Row::str($row, 'event_type'),
                event: Row::str($row, 'event'),
                sequencedAt: new DateTimeImmutable(Row::str($row, 'sequenced_at')),
                invalidated: Row::bool($row, 'invalidated'),
            );
        }

        return $events;
    }

    public function invalidateForDid(string $did): void
    {
        $this->db->execute(
            'UPDATE repo_seq SET invalidated = 1 WHERE did = ? AND invalidated = 0',
            [$did]
        );
    }
}
