<?php

declare(strict_types=1);

namespace Tests\Domain\Account;

use App\Domain\Account\AccountCreationResult;
use Tests\TestCase;

class AccountCreationResultTest extends TestCase
{
    public function testGetters(): void
    {
        $didDoc = ['id' => 'did:plc:alice'];
        $result = new AccountCreationResult(
            did: 'did:plc:alice',
            handle: 'alice.pds.test',
            didDoc: $didDoc,
        );

        $this->assertSame('did:plc:alice', $result->getDid());
        $this->assertSame('alice.pds.test', $result->getHandle());
        $this->assertSame($didDoc, $result->getDidDoc());
    }

    public function testGettersAllowNullDidDoc(): void
    {
        $result = new AccountCreationResult(
            did: 'did:web:alice.pds.test',
            handle: 'alice.pds.test',
            didDoc: null,
        );

        $this->assertNull($result->getDidDoc());
    }
}
