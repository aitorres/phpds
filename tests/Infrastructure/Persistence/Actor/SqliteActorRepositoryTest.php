<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Actor;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Actor\SqliteActorRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteActorRepositoryTest extends TestCase
{
    private function newRepo(): SqliteActorRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteActorRepository($db);
    }

    private function makeActor(string $did, ?string $handle): Actor
    {
        return new Actor($did, $handle, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function testFindAllReturnsProvidedActors(): void
    {
        $repo = $this->newRepo();
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repo->save($alice);

        $all = $repo->findAll();
        $this->assertCount(1, $all);
        $this->assertSame('did:web:alice.pds.test', $all[0]->getDid());
    }

    public function testFindActorByDid(): void
    {
        $repo = $this->newRepo();
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repo->save($alice);

        $found = $repo->findActorByDid('did:web:alice.pds.test');
        $this->assertSame('did:web:alice.pds.test', $found->getDid());
    }

    public function testFindActorByDidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(ActorNotFoundException::class);
        $repo->findActorByDid('did:web:missing.pds.test');
    }

    public function testFindActorByHandleIsCaseInsensitive(): void
    {
        $repo = $this->newRepo();
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repo->save($alice);

        $found = $repo->findActorByHandle('  ALICE.PDS.TEST  ');
        $this->assertSame('did:web:alice.pds.test', $found->getDid());
    }

    public function testFindActorByHandleThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(ActorNotFoundException::class);
        $repo->findActorByHandle('missing.pds.test');
    }

    public function testFindActorByHandleSkipsActorsWithoutHandle(): void
    {
        $repo = $this->newRepo();
        $headless = $this->makeActor('did:web:headless.pds.test', null);
        $repo->save($headless);

        $this->expectException(ActorNotFoundException::class);
        $repo->findActorByHandle('headless.pds.test');
    }

    public function testFindPageReturnsEmptyArrayWhenNoActors(): void
    {
        $repo = $this->newRepo();
        $this->assertSame([], $repo->findPage(null, 10));
    }

    public function testFindPageReturnsActorsOrderedByDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeActor('did:web:bob.pds.test', 'bob.pds.test'));
        $repo->save($this->makeActor('did:web:alice.pds.test', 'alice.pds.test'));
        $repo->save($this->makeActor('did:web:carol.pds.test', 'carol.pds.test'));

        $page = $repo->findPage(null, 10);
        $this->assertCount(3, $page);
        $this->assertSame('did:web:alice.pds.test', $page[0]->getDid());
        $this->assertSame('did:web:bob.pds.test', $page[1]->getDid());
        $this->assertSame('did:web:carol.pds.test', $page[2]->getDid());
    }

    public function testFindPageRespectsLimit(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeActor('did:web:alice.pds.test', 'alice.pds.test'));
        $repo->save($this->makeActor('did:web:bob.pds.test', 'bob.pds.test'));
        $repo->save($this->makeActor('did:web:carol.pds.test', 'carol.pds.test'));

        $page = $repo->findPage(null, 2);
        $this->assertCount(2, $page);
        $this->assertSame('did:web:alice.pds.test', $page[0]->getDid());
        $this->assertSame('did:web:bob.pds.test', $page[1]->getDid());
    }

    public function testFindPageStartsStrictlyAfterCursor(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeActor('did:web:alice.pds.test', 'alice.pds.test'));
        $repo->save($this->makeActor('did:web:bob.pds.test', 'bob.pds.test'));
        $repo->save($this->makeActor('did:web:carol.pds.test', 'carol.pds.test'));

        $page = $repo->findPage('did:web:alice.pds.test', 10);
        $this->assertCount(2, $page);
        $this->assertSame('did:web:bob.pds.test', $page[0]->getDid());
        $this->assertSame('did:web:carol.pds.test', $page[1]->getDid());
    }

    public function testFindPageReturnsEmptyForNonPositiveLimit(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeActor('did:web:alice.pds.test', 'alice.pds.test'));

        $this->assertSame([], $repo->findPage(null, 0));
        $this->assertSame([], $repo->findPage(null, -5));
    }
}
