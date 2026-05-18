<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\Device;
use App\Domain\OAuth\DeviceNotFoundException;
use App\Infrastructure\Persistence\OAuth\InMemoryDeviceRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryDeviceRepositoryTest extends TestCase
{
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
        $device = $this->makeDevice();
        $repo = new InMemoryDeviceRepository([$device]);

        $this->assertSame($device, $repo->findById('dev-1'));
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryDeviceRepository();

        $this->expectException(DeviceNotFoundException::class);
        $repo->findById('nope');
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = new InMemoryDeviceRepository();
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
        $repo = new InMemoryDeviceRepository([$this->makeDevice()]);
        $repo->deleteById('dev-1');

        $this->expectException(DeviceNotFoundException::class);
        $repo->findById('dev-1');
    }
}
