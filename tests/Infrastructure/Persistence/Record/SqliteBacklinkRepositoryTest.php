<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Record;

use App\Domain\Record\Backlink;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Record\SqliteBacklinkRepository;
use Tests\TestCase;

class SqliteBacklinkRepositoryTest extends TestCase
{
    private function newRepo(): SqliteBacklinkRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteBacklinkRepository($db);
    }

    public function testFindByUriAndLinkTo(): void
    {
        $repo = $this->newRepo();
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));
        $repo->save(new Backlink('at://alice/like/2', 'subject.uri', 'at://bob/post/1'));
        $repo->save(new Backlink('at://alice/like/3', 'subject.uri', 'at://carol/post/9'));

        $this->assertCount(1, $repo->findByUri('at://alice/like/1'));
        $this->assertCount(2, $repo->findByLinkTo('at://bob/post/1'));
        $this->assertCount(0, $repo->findByUri('nope'));
    }

    public function testSaveIsIdempotentForDuplicateTriple(): void
    {
        $repo = $this->newRepo();
        $bl = new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1');

        $repo->save($bl);
        $repo->save($bl);
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));

        $this->assertCount(1, $repo->findByUri('at://alice/like/1'));
    }

    public function testSaveAddsWhenPathOrLinkToDiffers(): void
    {
        $repo = $this->newRepo();
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));
        $repo->save(new Backlink('at://alice/like/1', 'reply.parent.uri', 'at://bob/post/1'));

        $this->assertCount(2, $repo->findByUri('at://alice/like/1'));
    }

    public function testDeleteByUri(): void
    {
        $repo = $this->newRepo();
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));
        $repo->save(new Backlink('at://alice/like/2', 'subject.uri', 'at://bob/post/1'));

        $repo->deleteByUri('at://alice/like/1');

        $this->assertCount(0, $repo->findByUri('at://alice/like/1'));
        $this->assertCount(1, $repo->findByLinkTo('at://bob/post/1'));
    }

    public function testDeleteAll(): void
    {
        $repo = $this->newRepo();
        $repo->save(new Backlink('at://alice/like/1', 'subject.uri', 'at://bob/post/1'));

        $repo->deleteAll();

        $this->assertCount(0, $repo->findByLinkTo('at://bob/post/1'));
    }
}
