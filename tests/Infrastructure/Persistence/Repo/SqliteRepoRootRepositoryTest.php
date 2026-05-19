<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Repo\SqliteRepoRootRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteRepoRootRepositoryTest extends TestCase
{
    private function newRepo(): SqliteRepoRootRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteRepoRootRepository($db);
    }

    private function makeRoot(string $did = 'did:plc:abc123'): RepoRoot
    {
        return new RepoRoot(
            did: $did,
            cid: 'bafyreiabc',
            rev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindByDidReturnsSeededRoot(): void
    {
        $repo = $this->newRepo();
        $repo->upsert($this->makeRoot());

        $found = $repo->findByDid('did:plc:abc123');
        $this->assertSame('did:plc:abc123', $found->getDid());
    }

    public function testFindByDidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(RepoRootNotFoundException::class);
        $repo->findByDid('did:plc:missing');
    }

    public function testUpsertStoresRoot(): void
    {
        $repo = $this->newRepo();
        $root = $this->makeRoot();
        $repo->upsert($root);

        $found = $repo->findByDid('did:plc:abc123');
        $this->assertSame('bafyreiabc', $found->getCid());
    }

    public function testUpsertOverwritesExisting(): void
    {
        $repo = $this->newRepo();
        $repo->upsert($this->makeRoot());

        $root2 = new RepoRoot('did:plc:abc123', 'bafyreinew', '3bbbb', new DateTimeImmutable());
        $repo->upsert($root2);

        $this->assertSame('bafyreinew', $repo->findByDid('did:plc:abc123')->getCid());
    }

    public function testDeleteByDid(): void
    {
        $repo = $this->newRepo();
        $repo->upsert($this->makeRoot());
        $repo->deleteByDid('did:plc:abc123');

        $this->expectException(RepoRootNotFoundException::class);
        $repo->findByDid('did:plc:abc123');
    }
}
