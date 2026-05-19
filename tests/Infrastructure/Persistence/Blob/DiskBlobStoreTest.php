<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Blob;

use App\Domain\Blob\BlobNotFoundException;
use App\Infrastructure\Persistence\Blob\DiskBlobStore;
use Tests\TestCase;

class DiskBlobStoreTest extends TestCase
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
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }

    private function newStore(): DiskBlobStore
    {
        return new DiskBlobStore($this->tmpDir);
    }

    public function testPutTempAndMakePermanent(): void
    {
        $store = $this->newStore();

        $tempKey = $store->putTemp('hello bytes');
        $this->assertTrue($store->hasTemp($tempKey));
        $this->assertFalse($store->hasStored('bafyreicid1'));

        $store->makePermanent($tempKey, 'bafyreicid1');

        $this->assertFalse($store->hasTemp($tempKey));
        $this->assertTrue($store->hasStored('bafyreicid1'));
        $this->assertSame('hello bytes', $store->getBytes('bafyreicid1'));
    }

    public function testMakePermanentThrowsForMissingTempKey(): void
    {
        $store = $this->newStore();

        $this->expectException(BlobNotFoundException::class);
        $store->makePermanent('no-such-key', 'bafyreicid2');
    }

    public function testPutPermanentAndGetStream(): void
    {
        $store = $this->newStore();
        $store->putPermanent('bafyreicid3', 'stream content');

        $stream = $store->getStream('bafyreicid3');
        $this->assertSame('stream content', (string) $stream);
    }

    public function testDeleteThrowsForMissing(): void
    {
        $store = $this->newStore();

        $this->expectException(BlobNotFoundException::class);
        $store->delete('bafyreinone');
    }

    public function testDeleteMany(): void
    {
        $store = $this->newStore();
        $store->putPermanent('cid1', 'a');
        $store->putPermanent('cid2', 'b');
        $store->deleteMany(['cid1', 'cid2']);

        $this->assertFalse($store->hasStored('cid1'));
        $this->assertFalse($store->hasStored('cid2'));
    }

    public function testQuarantineAndUnquarantine(): void
    {
        $store = $this->newStore();
        $store->putPermanent('cidq', 'data');

        $store->quarantine('cidq');
        $this->assertTrue($store->isQuarantined('cidq'));

        $store->unquarantine('cidq');
        $this->assertFalse($store->isQuarantined('cidq'));
    }

    public function testQuarantineThrowsForMissing(): void
    {
        $store = $this->newStore();

        $this->expectException(BlobNotFoundException::class);
        $store->quarantine('no-such-cid');
    }
}
