<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\Device;
use App\Domain\OAuth\DeviceNotFoundException;
use App\Domain\OAuth\DeviceRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteDeviceRepository implements DeviceRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findById(string $id): Device
    {
        $row = $this->db->fetchOne('SELECT * FROM device WHERE id = ?', [$id]);

        if ($row === null) {
            throw new DeviceNotFoundException();
        }

        return new Device(
            id: Row::str($row, 'id'),
            sessionId: Row::str($row, 'session_id'),
            userAgent: Row::nstr($row, 'user_agent'),
            ipAddress: Row::str($row, 'ip_address'),
            lastSeenAt: new DateTimeImmutable(Row::str($row, 'last_seen_at')),
        );
    }

    public function save(Device $device): void
    {
        $this->db->execute(
            'INSERT INTO device (id, session_id, user_agent, ip_address, last_seen_at)
             VALUES (:id, :session_id, :user_agent, :ip_address, :last_seen_at)
             ON CONFLICT(id) DO UPDATE SET
                session_id = excluded.session_id,
                user_agent = excluded.user_agent,
                ip_address = excluded.ip_address,
                last_seen_at = excluded.last_seen_at',
            [
                'id'           => $device->getId(),
                'session_id'   => $device->getSessionId(),
                'user_agent'   => $device->getUserAgent(),
                'ip_address'   => $device->getIpAddress(),
                'last_seen_at' => $device->getLastSeenAt()->format(DATE_ATOM),
            ]
        );
    }

    public function deleteById(string $id): void
    {
        $this->db->execute('DELETE FROM device WHERE id = ?', [$id]);
    }
}
