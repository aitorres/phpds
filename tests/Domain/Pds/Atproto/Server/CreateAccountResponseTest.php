<?php

declare(strict_types=1);

namespace Tests\Domain\Pds\Atproto\Server;

use App\Domain\Pds\Atproto\Server\CreateAccountResponse;
use Tests\TestCase;

class CreateAccountResponseTest extends TestCase
{
    public function testJsonSerializeIncludesAllPopulatedFields(): void
    {
        $didDoc = ['id' => 'did:plc:alice', '@context' => ['https://www.w3.org/ns/did/v1']];
        $response = new CreateAccountResponse(
            accessJwt: 'a.b.c',
            refreshJwt: 'd.e.f',
            handle: 'alice.pds.test',
            did: 'did:plc:alice',
            didDoc: $didDoc,
        );

        $this->assertSame('a.b.c', $response->getAccessJwt());
        $this->assertSame('d.e.f', $response->getRefreshJwt());
        $this->assertSame('alice.pds.test', $response->getHandle());
        $this->assertSame('did:plc:alice', $response->getDid());
        $this->assertSame($didDoc, $response->getDidDoc());
        $this->assertSame(
            [
                'accessJwt'  => 'a.b.c',
                'refreshJwt' => 'd.e.f',
                'handle'     => 'alice.pds.test',
                'did'        => 'did:plc:alice',
                'didDoc'     => $didDoc,
            ],
            $response->jsonSerialize()
        );
    }

    public function testJsonSerializeOmitsNullDidDoc(): void
    {
        $response = new CreateAccountResponse(
            accessJwt: 'access',
            refreshJwt: 'refresh',
            handle: 'bob.pds.test',
            did: 'did:web:bob.pds.test',
            didDoc: null,
        );

        $serialized = $response->jsonSerialize();
        $this->assertArrayNotHasKey('didDoc', $serialized);
        $this->assertSame('access', $serialized['accessJwt']);
        $this->assertSame('refresh', $serialized['refreshJwt']);
    }
}
