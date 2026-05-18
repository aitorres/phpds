<?php

declare(strict_types=1);

namespace Tests\Domain\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCode;
use DateTimeImmutable;
use Tests\TestCase;

class InviteCodeTest extends TestCase
{
    public function testGetters(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $code = new InviteCode(
            code: 'pds-test-abc123',
            availableUses: 5,
            disabled: false,
            forAccount: 'did:web:alice.pds.test',
            createdBy: 'admin',
            createdAt: $createdAt,
        );

        $this->assertSame('pds-test-abc123', $code->getCode());
        $this->assertSame(5, $code->getAvailableUses());
        $this->assertFalse($code->isDisabled());
        $this->assertSame('did:web:alice.pds.test', $code->getForAccount());
        $this->assertSame('admin', $code->getCreatedBy());
        $this->assertEquals($createdAt, $code->getCreatedAt());
    }

    public function testJsonSerialize(): void
    {
        $code = new InviteCode(
            code: 'pds-test-abc123',
            availableUses: 3,
            disabled: true,
            forAccount: 'did:web:alice.pds.test',
            createdBy: 'admin',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $json = json_decode((string) json_encode($code), true);

        $this->assertSame([
            'code'          => 'pds-test-abc123',
            'availableUses' => 3,
            'disabled'      => true,
            'forAccount'    => 'did:web:alice.pds.test',
            'createdBy'     => 'admin',
            'createdAt'     => '2026-01-01T00:00:00+00:00',
        ], $json);
    }
}
