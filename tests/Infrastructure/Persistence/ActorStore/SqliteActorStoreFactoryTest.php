<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\ActorStore;

use App\Domain\Record\Record;
use App\Infrastructure\Persistence\ActorStore\SqliteActorStoreFactory;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteActorStoreFactoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/phpds-test-' . uniqid('', true);
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            assert($file instanceof \SplFileInfo);
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function newFactory(): SqliteActorStoreFactory
    {
        return new SqliteActorStoreFactory(':memory:', $this->tmpDir . '/blobs');
    }

    public function testGetReturnsSameInstanceForSameDid(): void
    {
        $factory = $this->newFactory();

        $storeA1 = $factory->get('did:plc:alice');
        $storeA2 = $factory->get('did:plc:alice');

        $this->assertSame($storeA1, $storeA2);
    }

    public function testGetReturnsDifferentInstancesForDifferentDids(): void
    {
        $factory = $this->newFactory();

        $alice = $factory->get('did:plc:alice');
        $bob   = $factory->get('did:plc:bob');

        $this->assertNotSame($alice, $bob);
    }

    public function testStoresAreIsolated(): void
    {
        $factory = $this->newFactory();

        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/k1',
            cid: 'bafyreifoo',
            collection: 'app.bsky.feed.post',
            rkey: 'k1',
            repoRev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $factory->get('did:plc:alice')->getRecords()->save($record);

        $this->assertEmpty($factory->get('did:plc:bob')->getRecords()->findAll());
        $this->assertCount(1, $factory->get('did:plc:alice')->getRecords()->findAll());
    }

    public function testDestroyResetsState(): void
    {
        $factory = $this->newFactory();

        $record = new Record(
            uri: 'at://did:plc:alice/app.bsky.feed.post/k1',
            cid: 'bafyreifoo',
            collection: 'app.bsky.feed.post',
            rkey: 'k1',
            repoRev: '3aaaa',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $factory->get('did:plc:alice')->getRecords()->save($record);
        $factory->destroy('did:plc:alice');

        $this->assertEmpty($factory->get('did:plc:alice')->getRecords()->findAll());
    }

    public function testGetDid(): void
    {
        $factory = $this->newFactory();
        $store   = $factory->get('did:plc:alice');

        $this->assertSame('did:plc:alice', $store->getDid());
    }
}
