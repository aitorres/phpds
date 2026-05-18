<?php

declare(strict_types=1);

namespace Tests\Domain\Account\RefreshToken;

use App\Domain\Account\RefreshToken\RefreshToken;
use Tests\TestCase;

class RefreshTokenTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $token = new RefreshToken(
            id: 'rt-1',
            did: 'did:web:alice.pds.test',
            expiresAt: '2026-02-01T00:00:00Z',
            appPasswordName: 'phone',
            nextId: 'rt-2',
        );

        $this->assertSame('rt-1', $token->getId());
        $this->assertSame('did:web:alice.pds.test', $token->getDid());
        $this->assertSame('2026-02-01T00:00:00Z', $token->getExpiresAt());
        $this->assertSame('phone', $token->getAppPasswordName());
        $this->assertSame('rt-2', $token->getNextId());
    }

    public function testNullableFields(): void
    {
        $token = new RefreshToken(
            id: 'rt-1',
            did: 'did:web:alice.pds.test',
            expiresAt: '2026-02-01T00:00:00Z',
            appPasswordName: null,
            nextId: null,
        );

        $this->assertNull($token->getAppPasswordName());
        $this->assertNull($token->getNextId());
    }

    public function testJsonSerialize(): void
    {
        $token = new RefreshToken(
            id: 'rt-1',
            did: 'did:web:alice.pds.test',
            expiresAt: '2026-02-01T00:00:00Z',
            appPasswordName: 'phone',
            nextId: 'rt-2',
        );

        $this->assertSame([
            'id'              => 'rt-1',
            'did'             => 'did:web:alice.pds.test',
            'expiresAt'       => '2026-02-01T00:00:00Z',
            'appPasswordName' => 'phone',
            'nextId'          => 'rt-2',
        ], $token->jsonSerialize());
    }
}
