<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\Device;
use App\Domain\OAuth\DeviceNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\OAuth\SqliteDeviceRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteDeviceRepositoryTest extends TestCase
{
    private function newRepo(): SqliteDeviceRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteDeviceRepository($db);
    }

    private function makeDevice(string $id = 'dev-1'): Device
    {
        return new Device(
            id: $id,
            sessionId: 'sess-' . $id,
            userAgent: null,
            ipAddress: '127.0.0.1',
            lastSeenAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindById(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeDevice());

        $found = $repo->findById('dev-1');
        $this->assertSame('dev-1', $found->getId());
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(DeviceNotFoundException::class);
        $repo->findById('nope');
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeDevice());

        $updated = new Device(
            id: 'dev-1',
            sessionId: 'sess-updated',
            userAgent: 'Mozilla/5.0',
            ipAddress: '10.0.0.1',
            lastSeenAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
        );
        $repo->save($updated);

        $this->assertSame('sess-updated', $repo->findById('dev-1')->getSessionId());
    }

    public function testDeleteById(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeDevice());
        $repo->deleteById('dev-1');

        $this->expectException(DeviceNotFoundException::class);
        $repo->findById('dev-1');
    }
}
