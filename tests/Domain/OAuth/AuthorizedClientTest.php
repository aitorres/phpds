<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\AuthorizedClient;
use DateTimeImmutable;
use Tests\TestCase;

class AuthorizedClientTest extends TestCase
{
    public function testGetters(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00Z');
        $data = ['scopes' => ['atproto']];

        $client = new AuthorizedClient(
            did: 'did:plc:alice',
            clientId: 'https://app.test/client.json',
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            data: $data,
        );

        $this->assertSame('did:plc:alice', $client->getDid());
        $this->assertSame('https://app.test/client.json', $client->getClientId());
        $this->assertEquals($createdAt, $client->getCreatedAt());
        $this->assertEquals($updatedAt, $client->getUpdatedAt());
        $this->assertSame($data, $client->getData());
    }

    public function testJsonSerializeOmitsData(): void
    {
        $client = new AuthorizedClient(
            did: 'did:plc:alice',
            clientId: 'https://app.test/client.json',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            updatedAt: new DateTimeImmutable('2026-01-02T00:00:00Z'),
            data: ['secret' => 'shh'],
        );

        $json = json_decode((string) json_encode($client), true);

        $this->assertSame([
            'did'       => 'did:plc:alice',
            'clientId'  => 'https://app.test/client.json',
            'createdAt' => '2026-01-01T00:00:00+00:00',
            'updatedAt' => '2026-01-02T00:00:00+00:00',
        ], $json);
        $this->assertArrayNotHasKey('data', $json);
    }
}
