<?php

declare(strict_types=1);

namespace Tests\Domain\Record;

use App\Domain\Record\Record;
use DateTimeImmutable;
use Tests\TestCase;

class RecordTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $indexedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/3k',
            cid: 'bafy-record',
            collection: 'app.bsky.feed.post',
            rkey: '3k',
            repoRev: 'rev-1',
            indexedAt: $indexedAt,
            takedownRef: 'tk-1',
        );

        $this->assertSame('at://did:plc:alice/app.bsky.feed.post/3k', $record->getUri());
        $this->assertSame('bafy-record', $record->getCid());
        $this->assertSame('app.bsky.feed.post', $record->getCollection());
        $this->assertSame('3k', $record->getRkey());
        $this->assertSame('rev-1', $record->getRepoRev());
        $this->assertEquals($indexedAt, $record->getIndexedAt());
        $this->assertSame('tk-1', $record->getTakedownRef());
    }

    public function testTakedownRefDefaultsToNull(): void
    {
        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/3k',
            cid: 'bafy-record',
            collection: 'app.bsky.feed.post',
            rkey: '3k',
            repoRev: 'rev-1',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertNull($record->getTakedownRef());
    }

    public function testJsonSerialize(): void
    {
        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/3k',
            cid: 'bafy-record',
            collection: 'app.bsky.feed.post',
            rkey: '3k',
            repoRev: 'rev-1',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: null,
        );

        $this->assertSame([
            'uri'         => 'at://did:plc:alice/app.bsky.feed.post/3k',
            'cid'         => 'bafy-record',
            'collection'  => 'app.bsky.feed.post',
            'rkey'        => '3k',
            'repoRev'     => 'rev-1',
            'indexedAt'   => '2026-01-01T00:00:00+00:00',
            'takedownRef' => null,
        ], $record->jsonSerialize());
    }
}
