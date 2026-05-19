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
}
