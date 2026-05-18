<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizedClient;
use App\Domain\OAuth\AuthorizedClientNotFoundException;
use App\Infrastructure\Persistence\OAuth\InMemoryAuthorizedClientRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryAuthorizedClientRepositoryTest extends TestCase
{
    private function makeEntry(
        string $did = 'did:plc:alice',
        string $clientId = 'https://app.test/client.json',
    ): AuthorizedClient {
        return new AuthorizedClient(
            did: $did,
            clientId: $clientId,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            data: [],
        );
    }

    public function testFindByDidAndClientId(): void
    {
        $entry = $this->makeEntry();
        $repo = new InMemoryAuthorizedClientRepository([$entry]);

        $this->assertSame($entry, $repo->findByDidAndClientId('did:plc:alice', 'https://app.test/client.json'));
    }

    public function testFindByDidAndClientIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryAuthorizedClientRepository();

        $this->expectException(AuthorizedClientNotFoundException::class);
        $repo->findByDidAndClientId('did:plc:alice', 'nope');
    }

    public function testFindAllForDid(): void
    {
        $repo = new InMemoryAuthorizedClientRepository([
            $this->makeEntry('did:plc:alice', 'c1'),
            $this->makeEntry('did:plc:alice', 'c2'),
            $this->makeEntry('did:plc:bob', 'c3'),
        ]);

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = new InMemoryAuthorizedClientRepository();
        $repo->save($this->makeEntry());
        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));

        $updated = new AuthorizedClient(
            did: 'did:plc:alice',
            clientId: 'https://app.test/client.json',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            data: ['new' => true],
        );
        $repo->save($updated);

        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));
        $this->assertSame(
            ['new' => true],
            $repo->findByDidAndClientId('did:plc:alice', 'https://app.test/client.json')->getData()
        );
    }

    public function testDeleteByDidAndClientId(): void
    {
        $repo = new InMemoryAuthorizedClientRepository([
            $this->makeEntry('did:plc:alice', 'c1'),
            $this->makeEntry('did:plc:alice', 'c2'),
        ]);

        $repo->deleteByDidAndClientId('did:plc:alice', 'c1');

        $this->assertCount(1, $repo->findAllForDid('did:plc:alice'));
    }

    public function testDeleteAllForDid(): void
    {
        $repo = new InMemoryAuthorizedClientRepository([
            $this->makeEntry('did:plc:alice', 'c1'),
            $this->makeEntry('did:plc:alice', 'c2'),
            $this->makeEntry('did:plc:bob', 'c3'),
        ]);

        $repo->deleteAllForDid('did:plc:alice');

        $this->assertCount(0, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }
}
