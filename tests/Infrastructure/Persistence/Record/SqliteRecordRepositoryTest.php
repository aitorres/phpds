<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Record;

use App\Domain\Record\Record;
use App\Domain\Record\RecordNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Record\SqliteRecordRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteRecordRepositoryTest extends TestCase
{
    private function newRepo(): SqliteRecordRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteRecordRepository($db);
    }

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
        $repo = $this->newRepo();
        $repo->save($this->makeRecord());

        $found = $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/tid001');
        $this->assertSame('at://did:plc:alice/app.bsky.feed.post/tid001', $found->getUri());
    }

    public function testFindByUriThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(RecordNotFoundException::class);
        $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/missing');
    }

    public function testFindByCollection(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRecord(rkey: 'k1', collection: 'app.bsky.feed.post'));
        $repo->save($this->makeRecord(rkey: 'k2', collection: 'app.bsky.actor.profile'));

        $posts = $repo->findByCollection('app.bsky.feed.post');
        $this->assertCount(1, $posts);
        $this->assertSame('k1', $posts[0]->getRkey());
    }

    public function testFindAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRecord(rkey: 'k1'));
        $repo->save($this->makeRecord(rkey: 'k2'));

        $this->assertCount(2, $repo->findAll());
    }

    public function testDeleteByUri(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRecord());
        $repo->deleteByUri('at://did:plc:alice/app.bsky.feed.post/tid001');

        $this->expectException(RecordNotFoundException::class);
        $repo->findByUri('at://did:plc:alice/app.bsky.feed.post/tid001');
    }
}
