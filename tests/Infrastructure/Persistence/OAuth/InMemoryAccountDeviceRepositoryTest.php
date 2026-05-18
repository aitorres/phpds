<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AccountDevice;
use App\Domain\OAuth\AccountDeviceNotFoundException;
use App\Infrastructure\Persistence\OAuth\InMemoryAccountDeviceRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryAccountDeviceRepositoryTest extends TestCase
{
    private function makeEntry(string $did = 'did:plc:alice', string $deviceId = 'dev-1'): AccountDevice
    {
        return new AccountDevice(
            did: $did,
            deviceId: $deviceId,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindByDidAndDeviceId(): void
    {
        $entry = $this->makeEntry();
        $repo = new InMemoryAccountDeviceRepository([$entry]);

        $this->assertSame($entry, $repo->findByDidAndDeviceId('did:plc:alice', 'dev-1'));
    }

    public function testFindByDidAndDeviceIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryAccountDeviceRepository();

        $this->expectException(AccountDeviceNotFoundException::class);
        $repo->findByDidAndDeviceId('did:plc:alice', 'dev-1');
    }

    public function testFindAllForDid(): void
    {
        $repo = new InMemoryAccountDeviceRepository([
            $this->makeEntry('did:plc:alice', 'dev-1'),
            $this->makeEntry('did:plc:alice', 'dev-2'),
            $this->makeEntry('did:plc:bob', 'dev-3'),
        ]);

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
        $this->assertCount(0, $repo->findAllForDid('did:plc:carol'));
    }

    public function testSaveInsertsNewAndUpdatesExisting(): void
    {
        $repo = new InMemoryAccountDeviceRepository();
        $repo->save($this->makeEntry());
        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));

        $updated = new AccountDevice(
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
        );
        $repo->save($updated);

        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));
        $this->assertEquals(
            new DateTimeImmutable('2026-02-01T00:00:00Z'),
            $repo->findByDidAndDeviceId('did:plc:alice', 'dev-1')->getUpdatedAt(),
        );
    }

    public function testDeleteByDidAndDeviceId(): void
    {
        $repo = new InMemoryAccountDeviceRepository([
            $this->makeEntry('did:plc:alice', 'dev-1'),
            $this->makeEntry('did:plc:alice', 'dev-2'),
        ]);

        $repo->deleteByDidAndDeviceId('did:plc:alice', 'dev-1');

        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));
        $this->expectException(AccountDeviceNotFoundException::class);
        $repo->findByDidAndDeviceId('did:plc:alice', 'dev-1');
    }
}
