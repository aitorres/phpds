<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Record;

use App\Domain\Record\Record;
use App\Domain\Record\RecordNotFoundException;
use App\Infrastructure\Persistence\Record\InMemoryRecordRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryRecordRepositoryTest extends TestCase
{
    private function makeRecord(
        string $collection = 'app.bsky.feed.post',
        string $rkey = 'tid001',
    ): Record {
        $did = 'did:plc:alice';

        return new Record(
            uri: "at://{$did}/{$collection}/{$rkey}",
            cid: 'bafyreirecord',
            collection: $collection,
            rkey: $rkey,
            repoRev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindByUri(): void
    {
        $record = $this->makeRecord();
        $repo = new InMemoryRecordRepository([$record]);

        $this->assertSame($record, $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/tid001'));
    }

    public function testFindByUriThrowsWhenMissing(): void
    {
        $repo = new InMemoryRecordRepository();

        $this->expectException(RecordNotFoundException::class);
        $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/missing');
    }

    public function testFindByCollection(): void
    {
        $r1 = $this->makeRecord(rkey: 'k1', collection: 'app.bsky.feed.post');
        $r2 = $this->makeRecord(rkey: 'k2', collection: 'app.bsky.actor.profile');
        $repo = new InMemoryRecordRepository([$r1, $r2]);

        $posts = $repo->findByCollection('app.bsky.feed.post');
        $this->assertCount(1, $posts);
        $this->assertSame('k1', $posts[0]->getRkey());
    }

    public function testFindAll(): void
    {
        $r1 = $this->makeRecord(rkey: 'k1');
        $r2 = $this->makeRecord(rkey: 'k2');
        $repo = new InMemoryRecordRepository([$r1, $r2]);

        $this->assertCount(2, $repo->findAll());
    }

    public function testDeleteByUri(): void
    {
        $record = $this->makeRecord();
        $repo = new InMemoryRecordRepository([$record]);
        $repo->deleteByUri('at://did:plc:alice/app.bsky.feed.post/tid001');

        $this->expectException(RecordNotFoundException::class);
        $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/tid001');
    }
}
