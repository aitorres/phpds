<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\RepoRoot;
use DateTimeImmutable;
use Tests\TestCase;

class RepoRootTest extends TestCase
{
    public function testGetters(): void
    {
        $indexedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $root = new RepoRoot(
            did: 'did:plc:alice',
            cid: 'bafy-root',
            rev: 'rev-1',
            indexedAt: $indexedAt,
        );

        $this->assertSame('did:plc:alice', $root->getDid());
        $this->assertSame('bafy-root', $root->getCid());
        $this->assertSame('rev-1', $root->getRev());
        $this->assertEquals($indexedAt, $root->getIndexedAt());
    }

    public function testJsonSerialize(): void
    {
        $root = new RepoRoot(
            did: 'did:plc:alice',
            cid: 'bafy-root',
            rev: 'rev-1',
            indexedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertSame([
            'did'       => 'did:plc:alice',
            'cid'       => 'bafy-root',
            'rev'       => 'rev-1',
            'indexedAt' => '2026-01-01T00:00:00+00:00',
        ], $root->jsonSerialize());
    }
}
