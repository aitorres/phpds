<?php

declare(strict_types=1);

namespace Tests\Domain\Sequencer;

use App\Domain\Sequencer\RepoSeqEvent;
use DateTimeImmutable;
use Tests\TestCase;

class RepoSeqEventTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $sequencedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $event = new RepoSeqEvent(
            seq: 42,
            did: 'did:plc:alice',
            eventType: RepoSeqEvent::TYPE_APPEND,
            event: "\x00cbor",
            sequencedAt: $sequencedAt,
            invalidated: true,
        );

        $this->assertSame(42, $event->getSeq());
        $this->assertSame('did:plc:alice', $event->getDid());
        $this->assertSame(RepoSeqEvent::TYPE_APPEND, $event->getEventType());
        $this->assertSame("\x00cbor", $event->getEvent());
        $this->assertEquals($sequencedAt, $event->getSequencedAt());
        $this->assertTrue($event->isInvalidated());
    }

    public function testInvalidatedDefaultsToFalse(): void
    {
        $event = new RepoSeqEvent(
            seq: 1,
            did: 'did:plc:alice',
            eventType: RepoSeqEvent::TYPE_SYNC,
            event: '',
            sequencedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertFalse($event->isInvalidated());
    }

    public function testJsonSerializeOmitsEventBytes(): void
    {
        $event = new RepoSeqEvent(
            seq: 42,
            did: 'did:plc:alice',
            eventType: RepoSeqEvent::TYPE_APPEND,
            event: "\x00binary",
            sequencedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            invalidated: false,
        );

        $json = json_decode((string) json_encode($event), true);

        $this->assertSame([
            'seq'         => 42,
            'did'         => 'did:plc:alice',
            'eventType'   => RepoSeqEvent::TYPE_APPEND,
            'sequencedAt' => '2026-01-01T00:00:00+00:00',
            'invalidated' => false,
        ], $json);
        $this->assertArrayNotHasKey('event', $json);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('append', RepoSeqEvent::TYPE_APPEND);
        $this->assertSame('sync', RepoSeqEvent::TYPE_SYNC);
        $this->assertSame('identity', RepoSeqEvent::TYPE_IDENTITY);
        $this->assertSame('account', RepoSeqEvent::TYPE_ACCOUNT);
        $this->assertSame('tombstone', RepoSeqEvent::TYPE_TOMBSTONE);
    }
}
