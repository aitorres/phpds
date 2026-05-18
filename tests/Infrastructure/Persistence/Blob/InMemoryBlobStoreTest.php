<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Blob;

use App\Domain\Blob\BlobNotFoundException;
use App\Infrastructure\Persistence\Blob\InMemoryBlobStore;
use Tests\TestCase;

class InMemoryBlobStoreTest extends TestCase
{
    public function testPutTempAndMakePermanent(): void
    {
        $store = new InMemoryBlobStore();

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
        $store = new InMemoryBlobStore();

        $this->expectException(BlobNotFoundException::class);
        $store->makePermanent('no-such-key', 'bafyreicid2');
    }

    public function testPutPermanentAndGetStream(): void
    {
        $store = new InMemoryBlobStore();
        $store->putPermanent('bafyreicid3', 'stream content');

        $stream = $store->getStream('bafyreicid3');
        $this->assertSame('stream content', (string) $stream);
    }

    public function testDeleteThrowsForMissing(): void
    {
        $store = new InMemoryBlobStore();

        $this->expectException(BlobNotFoundException::class);
        $store->delete('bafyreinone');
    }

    public function testDeleteMany(): void
    {
        $store = new InMemoryBlobStore();
        $store->putPermanent('cid1', 'a');
        $store->putPermanent('cid2', 'b');
        $store->deleteMany(['cid1', 'cid2']);

        $this->assertFalse($store->hasStored('cid1'));
        $this->assertFalse($store->hasStored('cid2'));
    }

    public function testQuarantineAndUnquarantine(): void
    {
        $store = new InMemoryBlobStore();
        $store->putPermanent('cidq', 'data');

        $store->quarantine('cidq');
        $this->assertTrue($store->isQuarantined('cidq'));

        $store->unquarantine('cidq');
        $this->assertFalse($store->isQuarantined('cidq'));
    }

    public function testQuarantineThrowsForMissing(): void
    {
        $store = new InMemoryBlobStore();

        $this->expectException(BlobNotFoundException::class);
        $store->quarantine('no-such-cid');
    }
}
