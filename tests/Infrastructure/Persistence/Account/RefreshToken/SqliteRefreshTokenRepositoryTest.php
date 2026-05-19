<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\RefreshToken;

use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Account\RefreshToken\SqliteRefreshTokenRepository;
use Tests\TestCase;

class SqliteRefreshTokenRepositoryTest extends TestCase
{
    private function newRepo(): SqliteRefreshTokenRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteRefreshTokenRepository($db);
    }

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
        $repo = $this->newRepo();
        $repo->save($this->makeToken());

        $found = $repo->findById('token-abc');
        $this->assertSame('token-abc', $found->getId());
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(RefreshTokenNotFoundException::class);
        $repo->findById('nope');
    }

    public function testFindAllForDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken('t1', 'did:plc:alice'));
        $repo->save($this->makeToken('t2', 'did:plc:bob'));

        $results = $repo->findAllForDid('did:plc:alice');
        $this->assertCount(1, $results);
        $this->assertSame('t1', $results[0]->getId());
    }

    public function testDeleteAllForDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken('t1', 'did:plc:alice'));
        $repo->save($this->makeToken('t2', 'did:plc:alice'));

        $repo->deleteAllForDid('did:plc:alice');
        $this->assertEmpty($repo->findAllForDid('did:plc:alice'));
    }
}
