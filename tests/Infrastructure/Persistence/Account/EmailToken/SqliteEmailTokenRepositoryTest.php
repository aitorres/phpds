<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\EmailToken;

use App\Domain\Account\EmailToken\EmailToken;
use App\Domain\Account\EmailToken\EmailTokenNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Account\EmailToken\SqliteEmailTokenRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteEmailTokenRepositoryTest extends TestCase
{
    private function newRepo(): SqliteEmailTokenRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteEmailTokenRepository($db);
    }

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
        $repo = $this->newRepo();
        $t = $this->makeToken();
        $repo->save($t);

        $result = $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
        $this->assertSame('abc123', $result->getToken());
    }

    public function testFindByPurposeAndToken(): void
    {
        $repo = $this->newRepo();
        $t = $this->makeToken();
        $repo->save($t);

        $result = $repo->findByPurposeAndToken(EmailToken::PURPOSE_CONFIRM_EMAIL, 'abc123');
        $this->assertSame('did:plc:alice', $result->getDid());
    }

    public function testFindThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(EmailTokenNotFoundException::class);
        $repo->findByPurposeAndDid(EmailToken::PURPOSE_RESET_PASSWORD, 'did:plc:alice');
    }

    public function testSaveOverwritesPreviousForSamePurposeAndDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken(token: 'old'));
        $repo->save($this->makeToken(token: 'new'));

        $result = $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
        $this->assertSame('new', $result->getToken());
    }

    public function testDeleteByPurposeAndDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeToken());
        $repo->deleteByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');

        $this->expectException(EmailTokenNotFoundException::class);
        $repo->findByPurposeAndDid(EmailToken::PURPOSE_CONFIRM_EMAIL, 'did:plc:alice');
    }
}
