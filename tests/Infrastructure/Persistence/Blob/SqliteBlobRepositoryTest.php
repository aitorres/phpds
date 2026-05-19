<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Blob;

use App\Domain\Blob\Blob;
use App\Domain\Blob\BlobNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Blob\SqliteBlobRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteBlobRepositoryTest extends TestCase
{
    private function newRepo(): SqliteBlobRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteBlobRepository($db);
    }

    private function makeBlob(
        string $cid = 'bafyreib',
        ?string $tempKey = null,
    ): Blob {
        return new Blob(
            cid: $cid,
            mimeType: 'image/jpeg',
            size: 1024,
            tempKey: $tempKey,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindByCid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlob());

        $found = $repo->findByCid('bafyreib');
        $this->assertSame('bafyreib', $found->getCid());
    }

    public function testFindByCidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(BlobNotFoundException::class);
        $repo->findByCid('nope');
    }

    public function testFindTemporary(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlob(cid: 'cid1', tempKey: null));
        $repo->save($this->makeBlob(cid: 'cid2', tempKey: 'tmpkey123'));

        $temps = $repo->findTemporary();
        $this->assertCount(1, $temps);
        $this->assertSame('cid2', $temps[0]->getCid());
    }

    public function testFindAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlob(cid: 'c1'));
        $repo->save($this->makeBlob(cid: 'c2'));

        $this->assertCount(2, $repo->findAll());
    }

    public function testDeleteAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeBlob(cid: 'c1'));
        $repo->save($this->makeBlob(cid: 'c2'));
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
