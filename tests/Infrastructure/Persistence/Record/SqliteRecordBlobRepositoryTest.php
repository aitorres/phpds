<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Record;

use App\Domain\Record\RecordBlob;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Record\SqliteRecordBlobRepository;
use Tests\TestCase;

class SqliteRecordBlobRepositoryTest extends TestCase
{
    private function newRepo(): SqliteRecordBlobRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteRecordBlobRepository($db);
    }

    public function testFindByBlobCidAndRecordUri(): void
    {
        $repo = $this->newRepo();
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-2', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/2'));

        $this->assertCount(2, $repo->findByBlobCid('blob-1'));
        $this->assertCount(2, $repo->findByRecordUri('at://alice/post/1'));
        $this->assertCount(0, $repo->findByBlobCid('nope'));
    }

    public function testSaveIsIdempotent(): void
    {
        $repo = $this->newRepo();
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/1'));

        $this->assertCount(1, $repo->findByBlobCid('blob-1'));
    }

    public function testDeleteByRecordUri(): void
    {
        $repo = $this->newRepo();
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-2', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/2'));

        $repo->deleteByRecordUri('at://alice/post/1');

        $this->assertCount(0, $repo->findByRecordUri('at://alice/post/1'));
        $this->assertCount(1, $repo->findByRecordUri('at://alice/post/2'));
    }

    public function testDeleteByBlobCid(): void
    {
        $repo = $this->newRepo();
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/1'));
        $repo->save(new RecordBlob('blob-1', 'at://alice/post/2'));
        $repo->save(new RecordBlob('blob-2', 'at://alice/post/3'));

        $repo->deleteByBlobCid('blob-1');

        $this->assertCount(0, $repo->findByBlobCid('blob-1'));
        $this->assertCount(1, $repo->findByBlobCid('blob-2'));
    }
}
