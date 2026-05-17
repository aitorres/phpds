<?php

declare(strict_types=1);

namespace Tests\Domain\Account;

use App\Domain\Account\Account;
use DateTimeImmutable;
use Tests\TestCase;

class AccountTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $emailConfirmedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');

        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: 'alice@pds.test',
            passwordScrypt: 'hash',
            emailConfirmedAt: $emailConfirmedAt,
            invitesDisabled: true,
        );

        $this->assertSame('did:web:alice.pds.test', $account->getDid());
        $this->assertSame('alice@pds.test', $account->getEmail());
        $this->assertSame('hash', $account->getPasswordScrypt());
        $this->assertEquals($emailConfirmedAt, $account->getEmailConfirmedAt());
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

    public function testJsonSerializeFormatsDatetimeAndOmitsPasswordScrypt(): void
    {
        $account = new Account(
            did: 'did:web:alice.pds.test',
            email: 'alice@pds.test',
            passwordScrypt: 'secret',
            emailConfirmedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            invitesDisabled: false,
        );

        $json = json_decode((string) json_encode($account), true);

        $this->assertSame([
            'did' => 'did:web:alice.pds.test',
            'email' => 'alice@pds.test',
            'emailConfirmedAt' => '2026-01-01T00:00:00+00:00',
            'invitesDisabled' => false,
        ], $json);
        $this->assertArrayNotHasKey('passwordScrypt', $json);
    }
}
