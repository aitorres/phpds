<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Infrastructure\Persistence\Account\InviteCode\InMemoryInviteCodeRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryInviteCodeRepositoryTest extends TestCase
{
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
        $c1 = $this->makeCode('code1');
        $c2 = $this->makeCode('code2');
        $repo = new InMemoryInviteCodeRepository([$c1, $c2]);

        $this->assertCount(2, $repo->findAll());
    }

    public function testFindByCode(): void
    {
        $code = $this->makeCode();
        $repo = new InMemoryInviteCodeRepository([$code]);

        $this->assertSame($code, $repo->findByCode('pds-abc-1234'));
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = new InMemoryInviteCodeRepository();

        $this->expectException(InviteCodeNotFoundException::class);
        $repo->findByCode('no-such-code');
    }

    public function testFindAllForAccount(): void
    {
        $c1 = $this->makeCode('c1', 'did:plc:alice');
        $c2 = $this->makeCode('c2', 'did:plc:bob');
        $repo = new InMemoryInviteCodeRepository([$c1, $c2]);

        $results = $repo->findAllForAccount('did:plc:alice');
        $this->assertCount(1, $results);
        $this->assertSame('c1', $results[0]->getCode());
    }

    public function testDisable(): void
    {
        $code = $this->makeCode();
        $repo = new InMemoryInviteCodeRepository([$code]);
        $repo->disable('pds-abc-1234');

        $this->assertTrue($repo->findByCode('pds-abc-1234')->isDisabled());
    }

    public function testRecordUseAndFindUses(): void
    {
        $code = $this->makeCode();
        $repo = new InMemoryInviteCodeRepository([$code]);
        $use = new InviteCodeUse('pds-abc-1234', 'did:plc:bob', new DateTimeImmutable());
        $repo->recordUse($use);

        $uses = $repo->findUsesForCode('pds-abc-1234');
        $this->assertCount(1, $uses);
        $this->assertSame('did:plc:bob', $uses[0]->getUsedBy());
    }
}
