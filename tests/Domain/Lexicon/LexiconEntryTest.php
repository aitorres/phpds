<?php

declare(strict_types=1);

namespace Tests\Domain\Lexicon;

use App\Domain\Lexicon\LexiconEntry;
use DateTimeImmutable;
use Tests\TestCase;

class LexiconEntryTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00Z');
        $lastSucceededAt = new DateTimeImmutable('2026-01-02T01:00:00Z');
        $lex = ['lexicon' => 1, 'defs' => []];

        $entry = new LexiconEntry(
            nsid: 'com.example.foo',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            lastSucceededAt: $lastSucceededAt,
            uri: 'https://example.com/foo.json',
            lexicon: $lex,
        );

        $this->assertSame('com.example.foo', $entry->getNsid());
        $this->assertEquals($createdAt, $entry->getCreatedAt());
        $this->assertEquals($updatedAt, $entry->getUpdatedAt());
        $this->assertEquals($lastSucceededAt, $entry->getLastSucceededAt());
        $this->assertSame('https://example.com/foo.json', $entry->getUri());
        $this->assertSame($lex, $entry->getLexicon());
    }

    public function testNullableFields(): void
    {
        $entry = new LexiconEntry(
            nsid: 'com.example.foo',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            lastSucceededAt: null,
            uri: null,
            lexicon: null,
        );

        $this->assertNull($entry->getLastSucceededAt());
        $this->assertNull($entry->getUri());
        $this->assertNull($entry->getLexicon());
    }

    public function testJsonSerializeOmitsLexicon(): void
    {
        $entry = new LexiconEntry(
            nsid: 'com.example.foo',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            lastSucceededAt: new DateTimeImmutable('2026-01-02T01:00:00Z'),
            uri: 'https://example.com/foo.json',
            lexicon: ['lexicon' => 1],
        );

        $json = json_decode((string) json_encode($entry), true);

        $this->assertSame([
            'nsid'            => 'com.example.foo',
            'createdAt'       => '2026-01-01T00:00:00+00:00',
            'updatedAt'       => '2026-01-02T00:00:00+00:00',
            'lastSucceededAt' => '2026-01-02T01:00:00+00:00',
            'uri'             => 'https://example.com/foo.json',
        ], $json);
        $this->assertArrayNotHasKey('lexicon', $json);
    }

    public function testJsonSerializeWithNullLastSucceededAt(): void
    {
        $entry = new LexiconEntry(
            nsid: 'com.example.foo',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            lastSucceededAt: null,
            uri: null,
            lexicon: null,
        );

        $payload = $entry->jsonSerialize();

        $this->assertNull($payload['lastSucceededAt']);
        $this->assertNull($payload['uri']);
    }
}
