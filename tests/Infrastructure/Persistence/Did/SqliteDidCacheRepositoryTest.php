<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Did;

use App\Domain\Did\DidDocEntryNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\DidCacheSchema;
use App\Infrastructure\Persistence\Did\SqliteDidCacheRepository;
use Tests\TestCase;

class SqliteDidCacheRepositoryTest extends TestCase
{
    private function newRepo(): SqliteDidCacheRepository
    {
        $db = new Database(':memory:');
        DidCacheSchema::apply($db);

        return new SqliteDidCacheRepository($db);
    }

    public function testSetAndGet(): void
    {
        $repo = $this->newRepo();
        $doc = ['id' => 'did:plc:alice', '@context' => ['https://www.w3.org/ns/did/v1']];
        $repo->set('did:plc:alice', $doc);

        $entry = $repo->get('did:plc:alice');
        $this->assertSame('did:plc:alice', $entry->getDid());
        $this->assertSame($doc, $entry->getDoc());
    }

    public function testGetThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(DidDocEntryNotFoundException::class);
        $repo->get('did:plc:missing');
    }

    public function testHas(): void
    {
        $repo = $this->newRepo();
        $this->assertFalse($repo->has('did:plc:alice'));
        $repo->set('did:plc:alice', ['id' => 'did:plc:alice']);
        $this->assertTrue($repo->has('did:plc:alice'));
    }

    public function testClear(): void
    {
        $repo = $this->newRepo();
        $repo->set('did:plc:alice', ['id' => 'did:plc:alice']);
        $repo->clear('did:plc:alice');

        $this->assertFalse($repo->has('did:plc:alice'));
    }

    public function testClearAll(): void
    {
        $repo = $this->newRepo();
        $repo->set('did:plc:alice', ['id' => 'did:plc:alice']);
        $repo->set('did:plc:bob', ['id' => 'did:plc:bob']);
        $repo->clearAll();

        $this->assertFalse($repo->has('did:plc:alice'));
        $this->assertFalse($repo->has('did:plc:bob'));
    }

    public function testSetUpdatesExistingEntry(): void
    {
        $repo = $this->newRepo();
        $repo->set('did:plc:alice', ['id' => 'did:plc:alice', 'version' => 1]);
        $repo->set('did:plc:alice', ['id' => 'did:plc:alice', 'version' => 2]);

        $entry = $repo->get('did:plc:alice');
        $this->assertSame(2, $entry->getDoc()['version']);
    }
}
