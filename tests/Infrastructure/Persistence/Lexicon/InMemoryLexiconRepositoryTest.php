<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Lexicon;

use App\Domain\Lexicon\LexiconEntry;
use App\Domain\Lexicon\LexiconEntryNotFoundException;
use App\Infrastructure\Persistence\Lexicon\InMemoryLexiconRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryLexiconRepositoryTest extends TestCase
{
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
        $entry = $this->makeEntry();
        $repo = new InMemoryLexiconRepository([$entry]);

        $this->assertSame($entry, $repo->findByNsid('com.example.foo'));
    }

    public function testFindByNsidThrowsWhenMissing(): void
    {
        $repo = new InMemoryLexiconRepository();

        $this->expectException(LexiconEntryNotFoundException::class);
        $repo->findByNsid('nope');
    }

    public function testFindAll(): void
    {
        $repo = new InMemoryLexiconRepository([
            $this->makeEntry('com.example.a'),
            $this->makeEntry('com.example.b'),
        ]);

        $this->assertCount(2, $repo->findAll());
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = new InMemoryLexiconRepository();
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
        $repo = new InMemoryLexiconRepository([$this->makeEntry()]);
        $repo->deleteByNsid('com.example.foo');

        $this->assertCount(0, $repo->findAll());
    }
}
