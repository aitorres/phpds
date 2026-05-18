<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockNotFoundException;
use App\Infrastructure\Persistence\Repo\InMemoryRepoBlockRepository;
use Tests\TestCase;

class InMemoryRepoBlockRepositoryTest extends TestCase
{
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
        $block = $this->makeBlock();
        $repo = new InMemoryRepoBlockRepository([$block]);

        $this->assertSame($block, $repo->findByCid('bafyreiblock'));
    }

    public function testFindByCidThrowsWhenMissing(): void
    {
        $repo = new InMemoryRepoBlockRepository();

        $this->expectException(RepoBlockNotFoundException::class);
        $repo->findByCid('bafyreinone');
    }

    public function testFindByRepoRev(): void
    {
        $b1 = $this->makeBlock(cid: 'cid1', rev: 'rev-a');
        $b2 = $this->makeBlock(cid: 'cid2', rev: 'rev-b');
        $repo = new InMemoryRepoBlockRepository([$b1, $b2]);

        $results = $repo->findByRepoRev('rev-a');
        $this->assertCount(1, $results);
        $this->assertSame('cid1', $results[0]->getCid());
    }

    public function testSaveAndDeleteByCid(): void
    {
        $repo = new InMemoryRepoBlockRepository();
        $block = $this->makeBlock();
        $repo->save($block);
        $repo->deleteByCid('bafyreiblock');

        $this->expectException(RepoBlockNotFoundException::class);
        $repo->findByCid('bafyreiblock');
    }

    public function testDeleteAll(): void
    {
        $b1 = $this->makeBlock(cid: 'cid1');
        $b2 = $this->makeBlock(cid: 'cid2');
        $repo = new InMemoryRepoBlockRepository([$b1, $b2]);
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
