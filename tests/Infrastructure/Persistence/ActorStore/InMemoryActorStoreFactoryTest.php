<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\ActorStore;

use App\Domain\Record\Record;
use App\Infrastructure\Persistence\ActorStore\InMemoryActorStoreFactory;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryActorStoreFactoryTest extends TestCase
{
    public function testGetReturnsSameInstanceForSameDid(): void
    {
        $factory = new InMemoryActorStoreFactory();

        $storeA1 = $factory->get('did:plc:alice');
        $storeA2 = $factory->get('did:plc:alice');

        $this->assertSame($storeA1, $storeA2);
    }

    public function testGetReturnsDifferentInstancesForDifferentDids(): void
    {
        $factory = new InMemoryActorStoreFactory();

        $alice = $factory->get('did:plc:alice');
        $bob   = $factory->get('did:plc:bob');

        $this->assertNotSame($alice, $bob);
    }

    public function testStoresAreIsolated(): void
    {
        $factory = new InMemoryActorStoreFactory();

        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/k1',
            cid: 'bafyreifoo',
            collection: 'app.bsky.feed.post',
            rkey: 'k1',
            repoRev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $factory->get('did:plc:alice')->getRecords()->save($record);

        // Bob's store must not see Alice's record
        $this->assertEmpty($factory->get('did:plc:bob')->getRecords()->findAll());
        $this->assertCount(1, $factory->get('did:plc:alice')->getRecords()->findAll());
    }

    public function testDestroyResetsState(): void
    {
        $factory = new InMemoryActorStoreFactory();

        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/k1',
            cid: 'bafyreifoo',
            collection: 'app.bsky.feed.post',
            rkey: 'k1',
            repoRev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $factory->get('did:plc:alice')->getRecords()->save($record);
        $factory->destroy('did:plc:alice');

        // After destroy, a fresh empty store is created on next get
        $this->assertEmpty($factory->get('did:plc:alice')->getRecords()->findAll());
    }

    public function testGetDid(): void
    {
        $factory = new InMemoryActorStoreFactory();
        $store   = $factory->get('did:plc:alice');

        $this->assertSame('did:plc:alice', $store->getDid());
    }
}
