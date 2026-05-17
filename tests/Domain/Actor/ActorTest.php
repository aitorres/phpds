<?php

declare(strict_types=1);

namespace Tests\Domain\Actor;

use App\Domain\Actor\Actor;
use DateTimeImmutable;
use Tests\TestCase;

class ActorTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $deactivatedAt = new DateTimeImmutable('2026-02-01T00:00:00Z');
        $deleteAfter = new DateTimeImmutable('2026-03-01T00:00:00Z');

        $actor = new Actor(
            did: 'did:web:alice.pds.test',
            handle: 'alice.pds.test',
            createdAt: $createdAt,
            takedownRef: 'ref-1',
            deactivatedAt: $deactivatedAt,
            deleteAfter: $deleteAfter,
        );

        $this->assertSame('did:web:alice.pds.test', $actor->getDid());
        $this->assertSame('alice.pds.test', $actor->getHandle());
        $this->assertEquals($createdAt, $actor->getCreatedAt());
        $this->assertSame('ref-1', $actor->getTakedownRef());
        $this->assertEquals($deactivatedAt, $actor->getDeactivatedAt());
        $this->assertEquals($deleteAfter, $actor->getDeleteAfter());
    }

    public function testGettersWithNullableFieldsDefaultToNull(): void
    {
        $actor = new Actor(
            did: 'did:web:alice.pds.test',
            handle: null,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertNull($actor->getHandle());
        $this->assertNull($actor->getTakedownRef());
        $this->assertNull($actor->getDeactivatedAt());
        $this->assertNull($actor->getDeleteAfter());
    }

    public function testConstructorNormalizesHandle(): void
    {
        $actor = new Actor(
            did: 'did:web:alice.pds.test',
            handle: '  Alice.PDS.Test  ',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertSame('alice.pds.test', $actor->getHandle());
    }

    public function testJsonSerializeFormatsDatetimesAsRfc3339(): void
    {
        $actor = new Actor(
            did: 'did:web:alice.pds.test',
            handle: 'alice.pds.test',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: null,
            deactivatedAt: null,
            deleteAfter: null,
        );

        $payload = json_decode((string) json_encode($actor), true);

        $this->assertSame('did:web:alice.pds.test', $payload['did']);
        $this->assertSame('alice.pds.test', $payload['handle']);
        $this->assertSame('2026-01-01T00:00:00+00:00', $payload['createdAt']);
        $this->assertNull($payload['takedownRef']);
        $this->assertNull($payload['deactivatedAt']);
        $this->assertNull($payload['deleteAfter']);
    }
}
