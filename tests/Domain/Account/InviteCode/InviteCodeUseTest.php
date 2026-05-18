<?php

declare(strict_types=1);

namespace Tests\Domain\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCodeUse;
use DateTimeImmutable;
use Tests\TestCase;

class InviteCodeUseTest extends TestCase
{
    public function testGetters(): void
    {
        $usedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $use = new InviteCodeUse(
            code: 'pds-test-abc123',
            usedBy: 'did:web:alice.pds.test',
            usedAt: $usedAt,
        );

        $this->assertSame('pds-test-abc123', $use->getCode());
        $this->assertSame('did:web:alice.pds.test', $use->getUsedBy());
        $this->assertEquals($usedAt, $use->getUsedAt());
    }

    public function testJsonSerialize(): void
    {
        $use = new InviteCodeUse(
            code: 'pds-test-abc123',
            usedBy: 'did:web:alice.pds.test',
            usedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $json = json_decode((string) json_encode($use), true);

        $this->assertSame([
            'code'   => 'pds-test-abc123',
            'usedBy' => 'did:web:alice.pds.test',
            'usedAt' => '2026-01-01T00:00:00+00:00',
        ], $json);
    }
}
