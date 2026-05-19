<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizedClient;
use App\Domain\OAuth\AuthorizedClientNotFoundException;
use App\Domain\OAuth\AuthorizedClientRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteAuthorizedClientRepository implements AuthorizedClientRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByDidAndClientId(string $did, string $clientId): AuthorizedClient
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM authorized_client WHERE did = ? AND client_id = ?',
            [$did, $clientId]
        );

        if ($row === null) {
            throw new AuthorizedClientNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return AuthorizedClient[]
     */
    public function findAllForDid(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM authorized_client WHERE did = ? ORDER BY client_id',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(AuthorizedClient $authorizedClient): void
    {
        $this->db->execute(
            'INSERT INTO authorized_client (did, client_id, created_at, updated_at, data_json)
             VALUES (:did, :client_id, :created_at, :updated_at, :data_json)
             ON CONFLICT(did, client_id) DO UPDATE SET
                created_at = excluded.created_at,
                updated_at = excluded.updated_at,
                data_json = excluded.data_json',
            [
                'did'        => $authorizedClient->getDid(),
                'client_id'  => $authorizedClient->getClientId(),
                'created_at' => $authorizedClient->getCreatedAt()->format(DATE_ATOM),
                'updated_at' => $authorizedClient->getUpdatedAt()->format(DATE_ATOM),
                'data_json'  => json_encode($authorizedClient->getData(), JSON_THROW_ON_ERROR),
            ]
        );
    }

    public function deleteByDidAndClientId(string $did, string $clientId): void
    {
        $this->db->execute(
            'DELETE FROM authorized_client WHERE did = ? AND client_id = ?',
            [$did, $clientId]
        );
    }

    public function deleteAllForDid(string $did): void
    {
        $this->db->execute('DELETE FROM authorized_client WHERE did = ?', [$did]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuthorizedClient
    {
        $data = json_decode(Row::str($row, 'data_json'), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));
        /** @var array<string, mixed> $data */

        return new AuthorizedClient(
            did: Row::str($row, 'did'),
            clientId: Row::str($row, 'client_id'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            updatedAt: new DateTimeImmutable(Row::str($row, 'updated_at')),
            data: $data,
        );
    }
}
