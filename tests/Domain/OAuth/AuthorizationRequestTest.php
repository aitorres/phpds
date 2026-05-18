<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\AuthorizationRequest;
use DateTimeImmutable;
use Tests\TestCase;

class AuthorizationRequestTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $expiresAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $clientAuth = ['method' => 'none'];
        $params = ['scope' => 'atproto'];

        $req = new AuthorizationRequest(
            id: 'req-1',
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            clientId: 'https://app.test/client.json',
            clientAuth: $clientAuth,
            parameters: $params,
            expiresAt: $expiresAt,
            code: 'auth-code-1',
        );

        $this->assertSame('req-1', $req->getId());
        $this->assertSame('did:plc:alice', $req->getDid());
        $this->assertSame('dev-1', $req->getDeviceId());
        $this->assertSame('https://app.test/client.json', $req->getClientId());
        $this->assertSame($clientAuth, $req->getClientAuth());
        $this->assertSame($params, $req->getParameters());
        $this->assertEquals($expiresAt, $req->getExpiresAt());
        $this->assertSame('auth-code-1', $req->getCode());
    }

    public function testNullableFields(): void
    {
        $req = new AuthorizationRequest(
            id: 'req-1',
            did: null,
            deviceId: null,
            clientId: 'https://app.test/client.json',
            clientAuth: null,
            parameters: [],
            expiresAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            code: null,
        );

        $this->assertNull($req->getDid());
        $this->assertNull($req->getDeviceId());
        $this->assertNull($req->getClientAuth());
        $this->assertSame([], $req->getParameters());
        $this->assertNull($req->getCode());
    }

    public function testJsonSerializeOmitsClientAuthAndParameters(): void
    {
        $req = new AuthorizationRequest(
            id: 'req-1',
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            clientId: 'https://app.test/client.json',
            clientAuth: ['method' => 'secret'],
            parameters: ['scope' => 'atproto'],
            expiresAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            code: 'auth-code-1',
        );

        $json = json_decode((string) json_encode($req), true);

        $this->assertSame([
            'id'        => 'req-1',
            'did'       => 'did:plc:alice',
            'deviceId'  => 'dev-1',
            'clientId'  => 'https://app.test/client.json',
            'expiresAt' => '2026-01-01T00:00:00+00:00',
            'code'      => 'auth-code-1',
        ], $json);
        $this->assertArrayNotHasKey('clientAuth', $json);
        $this->assertArrayNotHasKey('parameters', $json);
    }
}
