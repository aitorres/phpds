<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Lexicon;

use App\Domain\Lexicon\LexiconEntry;
use App\Domain\Lexicon\LexiconEntryNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Lexicon\SqliteLexiconRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteLexiconRepositoryTest extends TestCase
{
    private function newRepo(): SqliteLexiconRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteLexiconRepository($db);
    }

    private function makeEntry(string $nsid = 'com.example.foo'): LexiconEntry
    {
        return new LexiconEntry(
            nsid: $nsid,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            lastSucceededAt: null,
            uri: null,
            lexicon: null,
        );
    }

    public function testFindByNsid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeEntry());

        $found = $repo->findByNsid('com.example.foo');
        $this->assertSame('com.example.foo', $found->getNsid());
    }

    public function testFindByNsidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(LexiconEntryNotFoundException::class);
        $repo->findByNsid('nope');
    }

    public function testFindAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeEntry('com.example.a'));
        $repo->save($this->makeEntry('com.example.b'));

        $this->assertCount(2, $repo->findAll());
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeEntry('com.example.a'));
        $this->assertCount(1, $repo->findAll());

        $updated = new LexiconEntry(
            nsid: 'com.example.a',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            lastSucceededAt: null,
            uri: 'https://example.com/a.json',
            lexicon: null,
        );
        $repo->save($updated);

        $this->assertCount(1, $repo->findAll());
        $this->assertSame('https://example.com/a.json', $repo->findByNsid('com.example.a')->getUri());
    }

    public function testDeleteByNsid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeEntry());
        $repo->deleteByNsid('com.example.foo');

        $this->assertCount(0, $repo->findAll());
    }
}
