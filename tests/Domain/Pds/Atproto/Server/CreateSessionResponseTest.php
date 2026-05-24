<?php

declare(strict_types=1);

namespace Tests\Domain\Pds\Atproto\Server;

use App\Domain\Pds\Atproto\Server\CreateSessionResponse;
use Tests\TestCase;

class CreateSessionResponseTest extends TestCase
{
    public function testJsonSerializeIncludesAllPopulatedFields(): void
    {
        $response = new CreateSessionResponse(
            accessJwt: 'a.b.c',
            refreshJwt: 'd.e.f',
            did: 'did:plc:alice',
            handle: 'alice.pds.test',
            email: 'alice@example.com',
            emailConfirmed: true,
            active: true,
            status: null,
        );

        $this->assertSame(
            [
                'accessJwt'      => 'a.b.c',
                'refreshJwt'     => 'd.e.f',
                'handle'         => 'alice.pds.test',
                'did'            => 'did:plc:alice',
                'emailConfirmed' => true,
                'active'         => true,
                'email'          => 'alice@example.com',
            ],
            $response->jsonSerialize()
        );
    }

    public function testJsonSerializeOmitsNullEmailAndIncludesStatusWhenInactive(): void
    {
        $response = new CreateSessionResponse(
            accessJwt: 'a',
            refreshJwt: 'b',
            did: 'did:plc:bob',
            handle: 'handle.invalid',
            email: null,
            emailConfirmed: false,
            active: false,
            status: 'deactivated',
        );

        $serialized = $response->jsonSerialize();
        $this->assertArrayNotHasKey('email', $serialized);
        $this->assertSame('deactivated', $serialized['status']);
        $this->assertFalse($serialized['active']);
    }
}
