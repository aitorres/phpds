<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Actor;

use App\Application\Settings\Settings;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Infrastructure\Persistence\Actor\InMemoryActorRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryActorRepositoryTest extends TestCase
{
    private function makeActor(string $did, ?string $handle): Actor
    {
        return new Actor($did, $handle, new DateTimeImmutable('2026-01-01T00:00:00Z'));
    }

    public function testFindAllReturnsProvidedActors(): void
    {
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repository = new InMemoryActorRepository(null, [$alice]);

        $this->assertEquals([$alice], $repository->findAll());
    }

    public function testFindAllSeedsFromSettings(): void
    {
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);
        $repository = new InMemoryActorRepository($settings);

        $actors = $repository->findAll();

        $this->assertCount(3, $actors);
        $this->assertSame('did:web:alice.pds.test', $actors[0]->getDid());
        $this->assertSame('alice.pds.test', $actors[0]->getHandle());
    }

    public function testFindActorByDid(): void
    {
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repository = new InMemoryActorRepository(null, [$alice]);

        $this->assertEquals($alice, $repository->findActorByDid('did:web:alice.pds.test'));
    }

    public function testFindActorByDidThrowsWhenMissing(): void
    {
        $repository = new InMemoryActorRepository(null, []);

        $this->expectException(ActorNotFoundException::class);
        $repository->findActorByDid('did:web:missing.pds.test');
    }

    public function testFindActorByHandleIsCaseInsensitive(): void
    {
        $alice = $this->makeActor('did:web:alice.pds.test', 'alice.pds.test');
        $repository = new InMemoryActorRepository(null, [$alice]);

        $this->assertEquals($alice, $repository->findActorByHandle('  ALICE.PDS.TEST  '));
    }

    public function testFindActorByHandleThrowsWhenMissing(): void
    {
        $repository = new InMemoryActorRepository(null, []);

        $this->expectException(ActorNotFoundException::class);
        $repository->findActorByHandle('missing.pds.test');
    }

    public function testFindActorByHandleSkipsActorsWithoutHandle(): void
    {
        $headless = $this->makeActor('did:web:headless.pds.test', null);
        $repository = new InMemoryActorRepository(null, [$headless]);

        $this->expectException(ActorNotFoundException::class);
        $repository->findActorByHandle('headless.pds.test');
    }
}
