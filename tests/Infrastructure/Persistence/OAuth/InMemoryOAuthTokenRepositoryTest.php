<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\OAuthToken;
use App\Domain\OAuth\OAuthTokenNotFoundException;
use App\Infrastructure\Persistence\OAuth\InMemoryOAuthTokenRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryOAuthTokenRepositoryTest extends TestCase
{
    private function makeToken(
        int $id = 1,
        string $tokenId = 'tok-1',
        string $did = 'did:plc:alice',
        ?string $code = 'code-1',
        ?string $refresh = 'refresh-1',
    ): OAuthToken {
        return new OAuthToken(
            id: $id,
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
        $token = $this->makeToken();
        $repo = new InMemoryOAuthTokenRepository([$token]);

        $this->assertSame($token, $repo->findByTokenId('tok-1'));
    }

    public function testFindByTokenIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryOAuthTokenRepository();

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByTokenId('nope');
    }

    public function testFindByCode(): void
    {
        $repo = new InMemoryOAuthTokenRepository([$this->makeToken()]);

        $this->assertSame('tok-1', $repo->findByCode('code-1')->getTokenId());
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = new InMemoryOAuthTokenRepository([$this->makeToken(code: null)]);

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByCode('nope');
    }

    public function testFindByRefreshToken(): void
    {
        $repo = new InMemoryOAuthTokenRepository([$this->makeToken()]);

        $this->assertSame('tok-1', $repo->findByRefreshToken('refresh-1')->getTokenId());
    }

    public function testFindByRefreshTokenThrowsWhenMissing(): void
    {
        $repo = new InMemoryOAuthTokenRepository([$this->makeToken(refresh: null)]);

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByRefreshToken('nope');
    }

    public function testFindAllForDid(): void
    {
        $repo = new InMemoryOAuthTokenRepository([
            $this->makeToken(id: 1, tokenId: 't1', did: 'did:plc:alice', code: 'c1', refresh: 'r1'),
            $this->makeToken(id: 2, tokenId: 't2', did: 'did:plc:alice', code: 'c2', refresh: 'r2'),
            $this->makeToken(id: 3, tokenId: 't3', did: 'did:plc:bob', code: 'c3', refresh: 'r3'),
        ]);

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = new InMemoryOAuthTokenRepository();
        $repo->save($this->makeToken());
        $repo->save($this->makeToken(code: 'updated'));

        $this->assertSame('updated', $repo->findByTokenId('tok-1')->getCode());
    }

    public function testDeleteByTokenId(): void
    {
        $repo = new InMemoryOAuthTokenRepository([$this->makeToken()]);
        $repo->deleteByTokenId('tok-1');

        $this->expectException(OAuthTokenNotFoundException::class);
        $repo->findByTokenId('tok-1');
    }

    public function testDeleteAllForDid(): void
    {
        $repo = new InMemoryOAuthTokenRepository([
            $this->makeToken(id: 1, tokenId: 't1', did: 'did:plc:alice', code: 'c1', refresh: 'r1'),
            $this->makeToken(id: 2, tokenId: 't2', did: 'did:plc:bob', code: 'c2', refresh: 'r2'),
        ]);

        $repo->deleteAllForDid('did:plc:alice');

        $this->assertCount(0, $repo->findAllForDid('did:plc:alice'));
        $this->assertCount(1, $repo->findAllForDid('did:plc:bob'));
    }
}
