<?php

declare(strict_types=1);

namespace Tests\Domain\Account\AppPassword;

use App\Domain\Account\AppPassword\AppPassword;
use DateTimeImmutable;
use Tests\TestCase;

class AppPasswordTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $appPassword = new AppPassword(
            did: 'did:web:alice.pds.test',
            name: 'phone',
            passwordScrypt: 'hash',
            createdAt: $createdAt,
            privileged: true,
        );

        $this->assertSame('did:web:alice.pds.test', $appPassword->getDid());
        $this->assertSame('phone', $appPassword->getName());
        $this->assertSame('hash', $appPassword->getPasswordScrypt());
        $this->assertEquals($createdAt, $appPassword->getCreatedAt());
        $this->assertTrue($appPassword->isPrivileged());
    }

    public function testPrivilegedDefaultsToFalse(): void
    {
        $appPassword = new AppPassword(
            did: 'did:web:alice.pds.test',
            name: 'phone',
            passwordScrypt: 'hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertFalse($appPassword->isPrivileged());
    }

    public function testJsonSerializeOmitsPasswordScryptAndFormatsDate(): void
    {
        $appPassword = new AppPassword(
            did: 'did:web:alice.pds.test',
            name: 'phone',
            passwordScrypt: 'secret',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            privileged: false,
        );

        $json = json_decode((string) json_encode($appPassword), true);

        $this->assertSame([
            'did'        => 'did:web:alice.pds.test',
            'name'       => 'phone',
            'createdAt'  => '2026-01-01T00:00:00+00:00',
            'privileged' => false,
        ], $json);
        $this->assertArrayNotHasKey('passwordScrypt', $json);
    }
}
