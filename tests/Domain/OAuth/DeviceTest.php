<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\Device;
use DateTimeImmutable;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $lastSeenAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $device = new Device(
            id: 'dev-1',
            sessionId: 'sess-1',
            userAgent: 'Mozilla/5.0',
            ipAddress: '127.0.0.1',
            lastSeenAt: $lastSeenAt,
        );

        $this->assertSame('dev-1', $device->getId());
        $this->assertSame('sess-1', $device->getSessionId());
        $this->assertSame('Mozilla/5.0', $device->getUserAgent());
        $this->assertSame('127.0.0.1', $device->getIpAddress());
        $this->assertEquals($lastSeenAt, $device->getLastSeenAt());
    }

    public function testUserAgentCanBeNull(): void
    {
        $device = new Device(
            id: 'dev-1',
            sessionId: 'sess-1',
            userAgent: null,
            ipAddress: '127.0.0.1',
            lastSeenAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertNull($device->getUserAgent());
    }

    public function testJsonSerialize(): void
    {
        $device = new Device(
            id: 'dev-1',
            sessionId: 'sess-1',
            userAgent: 'Mozilla/5.0',
            ipAddress: '127.0.0.1',
            lastSeenAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertSame([
            'id'          => 'dev-1',
            'sessionId'   => 'sess-1',
            'userAgent'   => 'Mozilla/5.0',
            'ipAddress'   => '127.0.0.1',
            'lastSeenAt'  => '2026-01-01T00:00:00+00:00',
        ], $device->jsonSerialize());
    }
}
