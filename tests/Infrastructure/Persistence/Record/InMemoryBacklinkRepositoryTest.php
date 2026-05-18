<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Record;

use App\Domain\Record\Backlink;
use App\Infrastructure\Persistence\Record\InMemoryBacklinkRepository;
use Tests\TestCase;

class InMemoryBacklinkRepositoryTest extends TestCase
{
    public function testFindByUriAndLinkTo(): void
    {
        $a = new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1');
        $b = new Backlink('at://alice/like/2', 'subject.uri', 'at://bob/post/1');
        $c = new Backlink('at://alice/like/3', 'subject.uri', 'at://carol/post/9');
        $repo = new InMemoryBacklinkRepository([$a, $b, $c]);

        $this->assertCount(1, $repo->findByUri('at://alice/like/1'));
        $this->assertCount(2, $repo->findByLinkTo('at://bob/post/1'));
        $this->assertCount(0, $repo->findByUri('nope'));
    }

    public function testSaveIsIdempotentForDuplicateTriple(): void
    {
        $repo = new InMemoryBacklinkRepository();
        $bl = new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1');

        $repo->save($bl);
        $repo->save($bl);
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));

        $this->assertCount(1, $repo->findByUri('at://alice/like/1'));
    }

    public function testSaveAddsWhenPathOrLinkToDiffers(): void
    {
        $repo = new InMemoryBacklinkRepository();
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));
        $repo->save(new Backlink('at://alice/like/1', 'reply.parent.uri', 'at://bob/post/1'));

        $this->assertCount(2, $repo->findByUri('at://alice/like/1'));
    }

    public function testDeleteByUri(): void
    {
        $repo = new InMemoryBacklinkRepository([
            new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'),
            new Backlink('at://alice/like/2', 'subject.uri', 'at://bob/post/1'),
        ]);

        $repo->deleteByUri('at://alice/like/1');

        $this->assertCount(0, $repo->findByUri('at://alice/like/1'));
        $this->assertCount(1, $repo->findByLinkTo('at://bob/post/1'));
    }

    public function testDeleteAll(): void
    {
        $repo = new InMemoryBacklinkRepository([
            new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'),
        ]);

        $repo->deleteAll();

        $this->assertCount(0, $repo->findByLinkTo('at://bob/post/1'));
    }
}
