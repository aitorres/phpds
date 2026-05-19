<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Repo\SqliteRepoBlockRepository;
use Tests\TestCase;

class SqliteRepoBlockRepositoryTest extends TestCase
{
    private function newRepo(): SqliteRepoBlockRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteRepoBlockRepository($db);
    }

    private function makeBlock(
        string $cid = 'bafyreiblock',
        string $rev = '3aaaa',
    ): RepoBlock {
        return new RepoBlock(
            cid: $cid,
            repoRev: $rev,
            size: 42,
            content: 'placeholder-cbor',
        );
    }

    public function testFindByCidReturnsSeededBlock(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlock());

        $found = $repo->findByCid('bafyreiblock');
        $this->assertSame('bafyreiblock', $found->getCid());
    }

    public function testFindByCidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(RepoBlockNotFoundException::class);
        $repo->findByCid('bafyreinone');
    }

    public function testFindByRepoRev(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlock(cid: 'cid1', rev: 'rev-a'));
        $repo->save($this->makeBlock(cid: 'cid2', rev: 'rev-b'));

        $results = $repo->findByRepoRev('rev-a');
        $this->assertCount(1, $results);
        $this->assertSame('cid1', $results[0]->getCid());
    }

    public function testSaveAndDeleteByCid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlock());
        $repo->deleteByCid('bafyreiblock');

        $this->expectException(RepoBlockNotFoundException::class);
        $repo->findByCid('bafyreiblock');
    }

    public function testDeleteAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlock(cid: 'cid1'));
        $repo->save($this->makeBlock(cid: 'cid2'));
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
