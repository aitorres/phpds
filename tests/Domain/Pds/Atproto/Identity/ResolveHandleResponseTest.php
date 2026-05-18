<?php

declare(strict_types=1);

namespace Tests\Domain\Pds\Atproto\Identity;

use App\Domain\Pds\Atproto\Identity\ResolveHandleResponse;
use Tests\TestCase;

class ResolveHandleResponseTest extends TestCase
{
    public function testGetDid(): void
    {
        $response = new ResolveHandleResponse('did:plc:alice');

        $this->assertSame('did:plc:alice', $response->getDid());
    }

    public function testJsonSerialize(): void
    {
        $response = new ResolveHandleResponse('did:plc:alice');

        $this->assertSame(['did' => 'did:plc:alice'], $response->jsonSerialize());
        $this->assertSame('{"did":"did:plc:alice"}', (string) json_encode($response));
    }
}
