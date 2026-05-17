<?php

declare(strict_types=1);

namespace Tests\Domain\Account;

use App\Domain\Account\Account;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: 'alice@pds.test',
            passwordScrypt: 'hash',
            emailConfirmedAt: '2024-01-01T00:00:00Z',
            invitesDisabled: true,
        );

        $this->assertSame('did:web:alice.pds.test', $account->getDid());
        $this->assertSame('alice@pds.test', $account->getEmail());
        $this->assertSame('hash', $account->getPasswordScrypt());
        $this->assertSame('2024-01-01T00:00:00Z', $account->getEmailConfirmedAt());
        $this->assertTrue($account->isInvitesDisabled());
    }

    public function testDefaultsForOptionalFields(): void
    {
        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: 'alice@pds.test',
            passwordScrypt: 'hash',
        );

        $this->assertNull($account->getEmailConfirmedAt());
        $this->assertFalse($account->isInvitesDisabled());
    }

    public function testConstructorNormalizesEmail(): void
    {
        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: '  Alice@PDS.test  ',
            passwordScrypt: 'hash',
        );

        $this->assertSame('alice@pds.test', $account->getEmail());
    }

    public function testJsonSerializeOmitsPasswordScrypt(): void
    {
        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: 'alice@pds.test',
            passwordScrypt: 'secret',
            emailConfirmedAt: '2024-01-01T00:00:00Z',
            invitesDisabled: false,
        );

        $json = json_decode((string) json_encode($account), true);

        $this->assertSame([
            'did' => 'did:web:alice.pds.test',
            'email' => 'alice@pds.test',
            'emailConfirmedAt' => '2024-01-01T00:00:00Z',
            'invitesDisabled' => false,
        ], $json);
        $this->assertArrayNotHasKey('passwordScrypt', $json);
    }
}
