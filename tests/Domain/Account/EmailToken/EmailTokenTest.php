<?php

declare(strict_types=1);

namespace Tests\Domain\Account\EmailToken;

use App\Domain\Account\EmailToken\EmailToken;
use DateTimeImmutable;
use Tests\TestCase;

class EmailTokenTest extends TestCase
{
    public function testGetters(): void
    {
        $requestedAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $token = new EmailToken(
            purpose: EmailToken::PURPOSE_CONFIRM_EMAIL,
            did: 'did:web:alice.pds.test',
            token: 'abc-123',
            requestedAt: $requestedAt,
        );

        $this->assertSame(EmailToken::PURPOSE_CONFIRM_EMAIL, $token->getPurpose());
        $this->assertSame('did:web:alice.pds.test', $token->getDid());
        $this->assertSame('abc-123', $token->getToken());
        $this->assertEquals($requestedAt, $token->getRequestedAt());
    }

    public function testJsonSerializeOmitsTokenAndFormatsDate(): void
    {
        $token = new EmailToken(
            purpose: EmailToken::PURPOSE_RESET_PASSWORD,
            did: 'did:web:alice.pds.test',
            token: 'secret-token',
            requestedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $json = json_decode((string) json_encode($token), true);

        $this->assertSame([
            'purpose'     => EmailToken::PURPOSE_RESET_PASSWORD,
            'did'         => 'did:web:alice.pds.test',
            'requestedAt' => '2026-01-01T00:00:00+00:00',
        ], $json);
        $this->assertArrayNotHasKey('token', $json);
    }

    public function testPurposeConstantsHaveExpectedValues(): void
    {
        $this->assertSame('confirm_email', EmailToken::PURPOSE_CONFIRM_EMAIL);
        $this->assertSame('update_email', EmailToken::PURPOSE_UPDATE_EMAIL);
        $this->assertSame('reset_password', EmailToken::PURPOSE_RESET_PASSWORD);
        $this->assertSame('delete_account', EmailToken::PURPOSE_DELETE_ACCOUNT);
        $this->assertSame('plc_operation', EmailToken::PURPOSE_PLC_OPERATION);
    }
}
