<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\RefreshToken;

use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenNotFoundException;
use App\Infrastructure\Persistence\Account\RefreshToken\InMemoryRefreshTokenRepository;
use Tests\TestCase;

class InMemoryRefreshTokenRepositoryTest extends TestCase
{
    private function makeToken(
        string $id = 'token-abc',
        string $did = 'did:plc:alice',
    ): RefreshToken {
        return new RefreshToken(
            id: $id,
            did: $did,
            expiresAt: '2099-01-01T00:00:00Z',
            appPasswordName: null,
            nextId: null,
        );
    }

    public function testFindById(): void
    {
        $token = $this->makeToken();
        $repo = new InMemoryRefreshTokenRepository([$token]);

        $this->assertSame($token, $repo->findById('token-abc'));
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryRefreshTokenRepository();

        $this->expectException(RefreshTokenNotFoundException::class);
        $repo->findById('nope');
    }

    public function testFindAllForDid(): void
    {
        $t1 = $this->makeToken('t1', 'did:plc:alice');
        $t2 = $this->makeToken('t2', 'did:plc:bob');
        $repo = new InMemoryRefreshTokenRepository([$t1, $t2]);

        $results = $repo->findAllForDid('did:plc:alice');
        $this->assertCount(1, $results);
        $this->assertSame('t1', $results[0]->getId());
    }

    public function testDeleteAllForDid(): void
    {
        $t1 = $this->makeToken('t1', 'did:plc:alice');
        $t2 = $this->makeToken('t2', 'did:plc:alice');
        $repo = new InMemoryRefreshTokenRepository([$t1, $t2]);

        $repo->deleteAllForDid('did:plc:alice');
        $this->assertEmpty($repo->findAllForDid('did:plc:alice'));
    }
}
