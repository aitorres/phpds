<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\EmailToken;

use App\Domain\Account\EmailToken\EmailToken;
use App\Domain\Account\EmailToken\EmailTokenNotFoundException;
use App\Infrastructure\Persistence\Account\EmailToken\InMemoryEmailTokenRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryEmailTokenRepositoryTest extends TestCase
{
    private function makeToken(
        string $purpose = EmailToken::PURPOSE_CONFIRM_EMAIL,
        string $did = 'did:plc:alice',
        string $token = 'abc123',
    ): EmailToken {
        return new EmailToken(
            purpose: $purpose,
            did: $did,
            token: $token,
            requestedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindByPurposeAndDid(): void
    {
        $t = $this->makeToken();
        $repo = new InMemoryEmailTokenRepository([$t]);

        $result = $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
        $this->assertSame($t, $result);
    }

    public function testFindByPurposeAndToken(): void
    {
        $t = $this->makeToken();
        $repo = new InMemoryEmailTokenRepository([$t]);

        $result = $repo->findByPurposeAndToken(EmailToken::PURPOSE_CONFIRM_EMAIL, 'abc123');
        $this->assertSame($t, $result);
    }

    public function testFindThrowsWhenMissing(): void
    {
        $repo = new InMemoryEmailTokenRepository();

        $this->expectException(EmailTokenNotFoundException::class);
        $repo->findByPurposeAndDid(EmailToken::PURPOSE_RESET_PASSWORD, 'did:plc:alice');
    }

    public function testSaveOverwritesPreviousForSamePurposeAndDid(): void
    {
        $t1 = $this->makeToken(token: 'old');
        $t2 = $this->makeToken(token: 'new');
        $repo = new InMemoryEmailTokenRepository([$t1]);
        $repo->save($t2);

        $result = $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
        $this->assertSame('new', $result->getToken());
    }

    public function testDeleteByPurposeAndDid(): void
    {
        $t = $this->makeToken();
        $repo = new InMemoryEmailTokenRepository([$t]);
        $repo->deleteByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');

        $this->expectException(EmailTokenNotFoundException::class);
        $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
    }
}
