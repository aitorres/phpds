<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\OAuthToken;
use DateTimeImmutable;
use Tests\TestCase;

class OAuthTokenTest extends TestCase
{
    private function makeToken(): OAuthToken
    {
        return new OAuthToken(
            id: 42,
            did: 'did:plc:alice',
            tokenId: 'tok-1',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            expiresAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            clientId: 'https://app.test/client.json',
            clientAuth: ['method' => 'none'],
            deviceId: 'dev-1',
            parameters: ['scope' => 'atproto'],
            details: ['extra' => true],
            code: 'auth-code',
            currentRefreshToken: 'refresh-1',
            scope: 'atproto',
        );
    }

    public function testGetters(): void
    {
        $token = $this->makeToken();

        $this->assertSame(42, $token->getId());
        $this->assertSame('did:plc:alice', $token->getDid());
        $this->assertSame('tok-1', $token->getTokenId());
        $this->assertEquals(new DateTimeImmutable('2026-01-01T00:00:00Z'), $token->getCreatedAt());
        $this->assertEquals(new DateTimeImmutable('2026-01-02T00:00:00Z'), $token->getUpdatedAt());
        $this->assertEquals(new DateTimeImmutable('2026-02-01T00:00:00Z'), $token->getExpiresAt());
        $this->assertSame('https://app.test/client.json', $token->getClientId());
        $this->assertSame(['method' => 'none'], $token->getClientAuth());
        $this->assertSame('dev-1', $token->getDeviceId());
        $this->assertSame(['scope' => 'atproto'], $token->getParameters());
        $this->assertSame(['extra' => true], $token->getDetails());
        $this->assertSame('auth-code', $token->getCode());
        $this->assertSame('refresh-1', $token->getCurrentRefreshToken());
        $this->assertSame('atproto', $token->getScope());
    }

    public function testNullableFields(): void
    {
        $token = new OAuthToken(
            id: 1,
            did: 'did:plc:alice',
            tokenId: 'tok-1',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            expiresAt: new DateTimeImmutable('2026-02-01T00:00:00Z'),
            clientId: 'https://app.test/client.json',
            clientAuth: [],
            deviceId: null,
            parameters: [],
            details: null,
            code: null,
            currentRefreshToken: null,
            scope: null,
        );

        $this->assertNull($token->getDeviceId());
        $this->assertNull($token->getDetails());
        $this->assertNull($token->getCode());
        $this->assertNull($token->getCurrentRefreshToken());
        $this->assertNull($token->getScope());
    }

    public function testJsonSerializeOmitsSensitiveJsonColumns(): void
    {
        $token = $this->makeToken();
        $json = json_decode((string) json_encode($token), true);

        $this->assertSame([
            'id'                  => 42,
            'did'                 => 'did:plc:alice',
            'tokenId'             => 'tok-1',
            'createdAt'           => '2026-01-01T00:00:00+00:00',
            'updatedAt'           => '2026-01-02T00:00:00+00:00',
            'expiresAt'           => '2026-02-01T00:00:00+00:00',
            'clientId'            => 'https://app.test/client.json',
            'deviceId'            => 'dev-1',
            'code'                => 'auth-code',
            'currentRefreshToken' => 'refresh-1',
            'scope'               => 'atproto',
        ], $json);
        $this->assertArrayNotHasKey('clientAuth', $json);
        $this->assertArrayNotHasKey('parameters', $json);
        $this->assertArrayNotHasKey('details', $json);
    }
}
