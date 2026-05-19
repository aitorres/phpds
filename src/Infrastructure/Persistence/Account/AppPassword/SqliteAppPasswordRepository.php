<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\AppPassword;

use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordNotFoundException;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteAppPasswordRepository implements AppPasswordRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return AppPassword[]
     */
    public function findAllForDid(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM app_password WHERE did = ? ORDER BY name',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function findByDidAndName(string $did, string $name): AppPassword
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM app_password WHERE did = ? AND name = ?',
            [$did, $name]
        );

        if ($row === null) {
            throw new AppPasswordNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(AppPassword $appPassword): void
    {
        $this->db->execute(
            'INSERT INTO app_password (did, name, password_scrypt, created_at, privileged)
             VALUES (:did, :name, :password_scrypt, :created_at, :privileged)
             ON CONFLICT(did, name) DO UPDATE SET
                password_scrypt = excluded.password_scrypt,
                created_at = excluded.created_at,
                privileged = excluded.privileged',
            [
                'did'             => $appPassword->getDid(),
                'name'            => $appPassword->getName(),
                'password_scrypt' => $appPassword->getPasswordScrypt(),
                'created_at'      => $appPassword->getCreatedAt()->format(DATE_ATOM),
                'privileged'      => $appPassword->isPrivileged() ? 1 : 0,
            ]
        );
    }

    public function deleteByDidAndName(string $did, string $name): void
    {
        $this->db->execute(
            'DELETE FROM app_password WHERE did = ? AND name = ?',
            [$did, $name]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AppPassword
    {
        return new AppPassword(
            did: Row::str($row, 'did'),
            name: Row::str($row, 'name'),
            passwordScrypt: Row::str($row, 'password_scrypt'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            privileged: Row::bool($row, 'privileged'),
        );
    }
}
