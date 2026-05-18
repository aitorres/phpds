<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\AccountDevice;
use DateTimeImmutable;
use Tests\TestCase;

class AccountDeviceTest extends TestCase
{
    public function testGetters(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00Z');
        $device = new AccountDevice(
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertSame('did:plc:alice', $device->getDid());
        $this->assertSame('dev-1', $device->getDeviceId());
        $this->assertEquals($createdAt, $device->getCreatedAt());
        $this->assertEquals($updatedAt, $device->getUpdatedAt());
    }

    public function testJsonSerialize(): void
    {
        $device = new AccountDevice(
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
        );

        $this->assertSame([
            'did'       => 'did:plc:alice',
            'deviceId'  => 'dev-1',
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'updatedAt' => '2026-01-02T00:00:00+00:00',
        ], $device->jsonSerialize());
    }
}
