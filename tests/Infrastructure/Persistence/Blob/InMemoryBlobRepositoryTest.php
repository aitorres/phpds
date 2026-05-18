<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Blob;

use App\Domain\Blob\Blob;
use App\Domain\Blob\BlobNotFoundException;
use App\Infrastructure\Persistence\Blob\InMemoryBlobRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryBlobRepositoryTest extends TestCase
{
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
        $blob = $this->makeBlob();
        $repo = new InMemoryBlobRepository([$blob]);

        $this->assertSame($blob, $repo->findByCid('bafyreib'));
    }

    public function testFindByCidThrowsWhenMissing(): void
    {
        $repo = new InMemoryBlobRepository();

        $this->expectException(BlobNotFoundException::class);
        $repo->findByCid('nope');
    }

    public function testFindTemporary(): void
    {
        $perm = $this->makeBlob(cid: 'cid1', tempKey: null);
        $temp = $this->makeBlob(cid: 'cid2', tempKey: 'tmpkey123');
        $repo = new InMemoryBlobRepository([$perm, $temp]);

        $temps = $repo->findTemporary();
        $this->assertCount(1, $temps);
        $this->assertSame('cid2', $temps[0]->getCid());
    }

    public function testFindAll(): void
    {
        $b1 = $this->makeBlob(cid: 'c1');
        $b2 = $this->makeBlob(cid: 'c2');
        $repo = new InMemoryBlobRepository([$b1, $b2]);

        $this->assertCount(2, $repo->findAll());
    }

    public function testDeleteAll(): void
    {
        $b1 = $this->makeBlob(cid: 'c1');
        $b2 = $this->makeBlob(cid: 'c2');
        $repo = new InMemoryBlobRepository([$b1, $b2]);
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
