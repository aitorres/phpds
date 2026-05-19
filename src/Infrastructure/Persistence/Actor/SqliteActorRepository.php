<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteActorRepository implements ActorRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return Actor[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM actor ORDER BY did');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function findActorByDid(string $did): Actor
    {
        $row = $this->db->fetchOne('SELECT * FROM actor WHERE did = ?', [trim($did)]);

        if ($row === null) {
            throw new ActorNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return Actor[]
     */
    public function findPage(?string $cursor, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        if ($cursor === null || $cursor === '') {
            $rows = $this->db->fetchAll(
                'SELECT * FROM actor ORDER BY did ASC LIMIT ?',
                [$limit]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT * FROM actor WHERE did > ? ORDER BY did ASC LIMIT ?',
                [$cursor, $limit]
            );
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function findActorByHandle(string $handle): Actor
    {
        $handle = strtolower(trim($handle));
        if ($handle === '') {
            throw new ActorNotFoundException();
        }

        $row = $this->db->fetchOne('SELECT * FROM actor WHERE handle = ?', [$handle]);

        if ($row === null) {
            throw new ActorNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(Actor $actor): void
    {
        $this->db->execute(
            'INSERT INTO actor (did, handle, created_at, takedown_ref, deactivated_at, delete_after)
             VALUES (:did, :handle, :created_at, :takedown_ref, :deactivated_at, :delete_after)
             ON CONFLICT(did) DO UPDATE SET
                handle = excluded.handle,
                created_at = excluded.created_at,
                takedown_ref = excluded.takedown_ref,
                deactivated_at = excluded.deactivated_at,
                delete_after = excluded.delete_after',
            [
                'did'            => $actor->getDid(),
                'handle'         => $actor->getHandle(),
                'created_at'     => $actor->getCreatedAt()->format(DATE_ATOM),
                'takedown_ref'   => $actor->getTakedownRef(),
                'deactivated_at' => $actor->getDeactivatedAt()?->format(DATE_ATOM),
                'delete_after'   => $actor->getDeleteAfter()?->format(DATE_ATOM),
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Actor
    {
        $deactivatedAt = Row::nstr($row, 'deactivated_at');
        $deleteAfter   = Row::nstr($row, 'delete_after');

        return new Actor(
            did: Row::str($row, 'did'),
            handle: Row::nstr($row, 'handle'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            takedownRef: Row::nstr($row, 'takedown_ref'),
            deactivatedAt: $deactivatedAt === null ? null : new DateTimeImmutable($deactivatedAt),
            deleteAfter: $deleteAfter === null ? null : new DateTimeImmutable($deleteAfter),
        );
    }
}
