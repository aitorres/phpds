<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\RefreshToken;

use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenNotFoundException;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;

class SqliteRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findById(string $id): RefreshToken
    {
        $row = $this->db->fetchOne('SELECT * FROM refresh_token WHERE id = ?', [$id]);

        if ($row === null) {
            throw new RefreshTokenNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return RefreshToken[]
     */
    public function findAllForDid(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM refresh_token WHERE did = ? ORDER BY id',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(RefreshToken $token): void
    {
        $this->db->execute(
            'INSERT INTO refresh_token (id, did, expires_at, app_password_name, next_id)
             VALUES (:id, :did, :expires_at, :app_password_name, :next_id)
             ON CONFLICT(id) DO UPDATE SET
                did = excluded.did,
                expires_at = excluded.expires_at,
                app_password_name = excluded.app_password_name,
                next_id = excluded.next_id',
            [
                'id'                => $token->getId(),
                'did'               => $token->getDid(),
                'expires_at'        => $token->getExpiresAt(),
                'app_password_name' => $token->getAppPasswordName(),
                'next_id'           => $token->getNextId(),
            ]
        );
    }

    public function deleteById(string $id): void
    {
        $this->db->execute('DELETE FROM refresh_token WHERE id = ?', [$id]);
    }

    public function deleteAllForDid(string $did): void
    {
        $this->db->execute('DELETE FROM refresh_token WHERE did = ?', [$did]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RefreshToken
    {
        return new RefreshToken(
            id: Row::str($row, 'id'),
            did: Row::str($row, 'did'),
            expiresAt: Row::str($row, 'expires_at'),
            appPasswordName: Row::nstr($row, 'app_password_name'),
            nextId: Row::nstr($row, 'next_id'),
        );
    }
}
