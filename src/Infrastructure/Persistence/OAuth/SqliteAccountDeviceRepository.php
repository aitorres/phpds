<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AccountDevice;
use App\Domain\OAuth\AccountDeviceNotFoundException;
use App\Domain\OAuth\AccountDeviceRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteAccountDeviceRepository implements AccountDeviceRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByDidAndDeviceId(string $did, string $deviceId): AccountDevice
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM account_device WHERE did = ? AND device_id = ?',
            [$did, $deviceId]
        );

        if ($row === null) {
            throw new AccountDeviceNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @return AccountDevice[]
     */
    public function findAllForDid(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM account_device WHERE did = ? ORDER BY device_id',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(AccountDevice $accountDevice): void
    {
        $this->db->execute(
            'INSERT INTO account_device (did, device_id, created_at, updated_at)
             VALUES (:did, :device_id, :created_at, :updated_at)
             ON CONFLICT(did, device_id) DO UPDATE SET
                created_at = excluded.created_at,
                updated_at = excluded.updated_at',
            [
                'did'        => $accountDevice->getDid(),
                'device_id'  => $accountDevice->getDeviceId(),
                'created_at' => $accountDevice->getCreatedAt()->format(DATE_ATOM),
                'updated_at' => $accountDevice->getUpdatedAt()->format(DATE_ATOM),
            ]
        );
    }

    public function deleteByDidAndDeviceId(string $did, string $deviceId): void
    {
        $this->db->execute(
            'DELETE FROM account_device WHERE did = ? AND device_id = ?',
            [$did, $deviceId]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AccountDevice
    {
        return new AccountDevice(
            did: Row::str($row, 'did'),
            deviceId: Row::str($row, 'device_id'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            updatedAt: new DateTimeImmutable(Row::str($row, 'updated_at')),
        );
    }
}
