<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootNotFoundException;
use App\Infrastructure\Persistence\Repo\InMemoryRepoRootRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryRepoRootRepositoryTest extends TestCase
{
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
        $root = $this->makeRoot();
        $repo = new InMemoryRepoRootRepository([$root]);

        $this->assertSame($root, $repo->findByDid('did:plc:abc123'));
    }

    public function testFindByDidThrowsWhenMissing(): void
    {
        $repo = new InMemoryRepoRootRepository();

        $this->expectException(RepoRootNotFoundException::class);
        $repo->findByDid('did:plc:missing');
    }

    public function testUpsertStoresRoot(): void
    {
        $repo = new InMemoryRepoRootRepository();
        $root = $this->makeRoot();
        $repo->upsert($root);

        $this->assertSame($root, $repo->findByDid('did:plc:abc123'));
    }

    public function testUpsertOverwritesExisting(): void
    {
        $root1 = $this->makeRoot();
        $repo = new InMemoryRepoRootRepository([$root1]);

        $root2 = new RepoRoot('did:plc:abc123', 'bafyreinew', '3bbbb', new DateTimeImmutable());
        $repo->upsert($root2);

        $this->assertSame('bafyreinew', $repo->findByDid('did:plc:abc123')->getCid());
    }

    public function testDeleteByDid(): void
    {
        $root = $this->makeRoot();
        $repo = new InMemoryRepoRootRepository([$root]);
        $repo->deleteByDid('did:plc:abc123');

        $this->expectException(RepoRootNotFoundException::class);
        $repo->findByDid('did:plc:abc123');
    }
}
