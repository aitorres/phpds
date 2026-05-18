<?php

declare(strict_types=1);

namespace Tests\Domain\Did;

use App\Domain\Did\DidDocEntry;
use DateTimeImmutable;
use Tests\TestCase;

class DidDocEntryTest extends TestCase
{
    public function testGetters(): void
    {
        $updatedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $doc = ['id' => 'did:plc:alice', 'verificationMethod' => []];

        $entry = new DidDocEntry(
            did: 'did:plc:alice',
            doc: $doc,
            updatedAt: $updatedAt,
        );

        $this->assertSame('did:plc:alice', $entry->getDid());
        $this->assertSame($doc, $entry->getDoc());
        $this->assertEquals($updatedAt, $entry->getUpdatedAt());
    }

    public function testJsonSerialize(): void
    {
        $entry = new DidDocEntry(
            did: 'did:plc:alice',
            doc: ['id' => 'did:plc:alice'],
            updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertSame([
            'did'       => 'did:plc:alice',
            'doc'       => ['id' => 'did:plc:alice'],
            'updatedAt' => '2026-01-01T00:00:00+00:00',
        ], $entry->jsonSerialize());
    }
}
