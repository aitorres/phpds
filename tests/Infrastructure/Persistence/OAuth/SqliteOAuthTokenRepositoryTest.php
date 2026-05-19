<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\OAuthToken;
use App\Domain\OAuth\OAuthTokenNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\OAuth\SqliteOAuthTokenRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteOAuthTokenRepositoryTest extends TestCase
{
    private function newRepo(): SqliteOAuthTokenRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteOAuthTokenRepository($db);
    }

    private function makeToken(
        string $tokenId = 'tok-1',
        string $did = 'did:plc:alice',
        ?string $code = 'code-1',
        ?string $refresh = 'refresh-1',
    ): OAuthToken {
        return new OAuthToken(
            id: 0,
            did: $did,
            tokenId: $tokenId,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            expiresAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            clientId: 'https://app.test/client.json',
            clientAuth: [],
            deviceId: null,
            parameters: [],
            details: null,
            code: $code,
            currentRefreshToken: $refresh,
            scope: null,
        );
    }

    public function testFindByTokenId(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());

        $found = $repo->findByTokenId('tok-1');
        $this->assertSame('tok-1', $found->getTokenId());
    }

    public function testFindByTokenIdThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByTokenId('nope');
    }

    public function testFindByCode(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());

        $this->assertSame('tok-1', $repo->findByCode('code-1')->getTokenId());
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken(code: null));

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByCode('nope');
    }

    public function testFindByRefreshToken(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());

        $this->assertSame('tok-1', $repo->findByRefreshToken('refresh-1')->getTokenId());
    }

    public function testFindByRefreshTokenThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken(refresh: null));

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByRefreshToken('nope');
    }

    public function testFindAllForDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken(tokenId: 't1', did: 'did:plc:alice', code: 'c1', refresh: 'r1'));
        $repo->save($this->makeToken(tokenId: 't2', did: 'did:plc:alice', code: 'c2', refresh: 'r2'));
        $repo->save($this->makeToken(tokenId: 't3', did: 'did:plc:bob', code: 'c3', refresh: 'r3'));

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());
        $repo->save($this->makeToken(code: 'updated'));

        $this->assertSame('updated', $repo->findByTokenId('tok-1')->getCode());
    }

    public function testDeleteByTokenId(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());
        $repo->deleteByTokenId('tok-1');

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByTokenId('tok-1');
    }

    public function testDeleteAllForDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken(tokenId: 't1', did: 'did:plc:alice', code: 'c1', refresh: 'r1'));
        $repo->save($this->makeToken(tokenId: 't2', did: 'did:plc:bob', code: 'c2', refresh: 'r2'));

        $repo->deleteAllForDid('did:plc:alice');

        $this->assertCount(0, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }
}
