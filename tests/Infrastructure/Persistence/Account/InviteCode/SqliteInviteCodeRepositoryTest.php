<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Account\InviteCode\SqliteInviteCodeRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteInviteCodeRepositoryTest extends TestCase
{
    private function newRepo(): SqliteInviteCodeRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteInviteCodeRepository($db);
    }

    private function makeCode(
        string $code = 'pds-abc-1234',
        string $forAccount = 'did:plc:alice',
    ): InviteCode {
        return new InviteCode(
            code: $code,
            availableUses: 5,
            disabled: false,
            forAccount: $forAccount,
            createdBy: 'admin',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('code1'));
        $repo->save($this->makeCode('code2'));

        $this->assertCount(2, $repo->findAll());
    }

    public function testFindByCode(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode());

        $found = $repo->findByCode('pds-abc-1234');
        $this->assertSame('pds-abc-1234', $found->getCode());
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(InviteCodeNotFoundException::class);
        $repo->findByCode('no-such-code');
    }

    public function testFindAllForAccount(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('c1', 'did:plc:alice'));
        $repo->save($this->makeCode('c2', 'did:plc:bob'));

        $results = $repo->findAllForAccount('did:plc:alice');
        $this->assertCount(1, $results);
        $this->assertSame('c1', $results[0]->getCode());
    }

    public function testDisable(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode());
        $repo->disable('pds-abc-1234');

        $this->assertTrue($repo->findByCode('pds-abc-1234')->isDisabled());
    }

    public function testRecordUseAndFindUses(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode());
        $use = new InviteCodeUse('pds-abc-1234', 'did:plc:bob', new DateTimeImmutable('2026-01-02T00:00:00Z'));
        $repo->recordUse($use);

        $uses = $repo->findUsesForCode('pds-abc-1234');
        $this->assertCount(1, $uses);
        $this->assertSame('did:plc:bob', $uses[0]->getUsedBy());
    }
}
