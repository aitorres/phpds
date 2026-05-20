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
        ?DateTimeImmutable $createdAt = null,
        int $availableUses = 5,
    ): InviteCode {
        return new InviteCode(
            code: $code,
            availableUses: $availableUses,
            disabled: false,
            forAccount: $forAccount,
            createdBy: 'admin',
            createdAt: $createdAt ?? new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    private function makeUse(string $code, string $usedBy, string $usedAt): InviteCodeUse
    {
        return new InviteCodeUse($code, $usedBy, new DateTimeImmutable($usedAt));
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
        $use = $this->makeUse('pds-abc-1234', 'did:plc:invite-user', '2026-01-02T00:00:00Z');
        $repo->recordUse($use);

        $uses = $repo->findUsesForCode('pds-abc-1234');
        $this->assertCount(1, $uses);
        $this->assertSame('did:plc:invite-user', $uses[0]->getUsedBy());
    }

    public function testFindPageByRecentOrdersNewestFirst(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('old', createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z')));
        $repo->save($this->makeCode('newest', createdAt: new DateTimeImmutable('2026-01-03T00:00:00Z')));
        $repo->save($this->makeCode('middle', createdAt: new DateTimeImmutable('2026-01-02T00:00:00Z')));

        $page = $repo->findPageByRecent(null, null, 2);

        $this->assertSame(['newest', 'middle'], array_map(
            static fn (InviteCode $code): string => $code->getCode(),
            $page
        ));
    }

    public function testFindPageByRecentAppliesCursor(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('old', createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z')));
        $repo->save($this->makeCode('middle', createdAt: new DateTimeImmutable('2026-01-02T00:00:00Z')));
        $repo->save($this->makeCode('newest', createdAt: new DateTimeImmutable('2026-01-03T00:00:00Z')));

        $page = $repo->findPageByRecent('2026-01-02T00:00:00+00:00', 'middle', 5);

        $this->assertSame(['old'], array_map(
            static fn (InviteCode $code): string => $code->getCode(),
            $page
        ));
    }

    public function testFindPageByUsageOrdersMostUsedFirst(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('one'));
        $repo->save($this->makeCode('three'));
        $repo->save($this->makeCode('two'));

        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-1', '2026-01-03T00:00:00Z'));
        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-2', '2026-01-03T01:00:00Z'));
        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-3', '2026-01-03T02:00:00Z'));
        $repo->recordUse($this->makeUse('two', 'did:plc:two-user-1', '2026-01-03T03:00:00Z'));
        $repo->recordUse($this->makeUse('two', 'did:plc:two-user-2', '2026-01-03T04:00:00Z'));
        $repo->recordUse($this->makeUse('one', 'did:plc:one-user-1', '2026-01-03T05:00:00Z'));

        $page = $repo->findPageByUsage(null, null, 3);

        $this->assertSame(['three', 'two', 'one'], array_map(
            static fn (InviteCode $code): string => $code->getCode(),
            $page
        ));
    }

    public function testFindPageByUsageAppliesCursor(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('one'));
        $repo->save($this->makeCode('three'));
        $repo->save($this->makeCode('two'));

        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-1', '2026-01-03T00:00:00Z'));
        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-2', '2026-01-03T01:00:00Z'));
        $repo->recordUse($this->makeUse('three', 'did:plc:three-user-3', '2026-01-03T02:00:00Z'));
        $repo->recordUse($this->makeUse('two', 'did:plc:two-user-1', '2026-01-03T03:00:00Z'));
        $repo->recordUse($this->makeUse('two', 'did:plc:two-user-2', '2026-01-03T04:00:00Z'));
        $repo->recordUse($this->makeUse('one', 'did:plc:one-user-1', '2026-01-03T05:00:00Z'));

        $page = $repo->findPageByUsage(2, 'two', 3);

        $this->assertSame(['one'], array_map(
            static fn (InviteCode $code): string => $code->getCode(),
            $page
        ));
    }

    public function testFindUsesForCodesGroupsByCodeAndOrdersNewestFirst(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeCode('alpha'));
        $repo->save($this->makeCode('beta'));

        $repo->recordUse($this->makeUse('alpha', 'did:plc:alpha-user-1', '2026-01-01T00:00:00Z'));
        $repo->recordUse($this->makeUse('alpha', 'did:plc:alpha-user-2', '2026-01-02T00:00:00Z'));
        $repo->recordUse($this->makeUse('beta', 'did:plc:beta-user-1', '2026-01-03T00:00:00Z'));

        $uses = $repo->findUsesForCodes(['alpha', 'beta']);

        $this->assertSame(['did:plc:alpha-user-2', 'did:plc:alpha-user-1'], array_map(
            static fn (InviteCodeUse $use): string => $use->getUsedBy(),
            $uses['alpha']
        ));
        $this->assertSame(['did:plc:beta-user-1'], array_map(
            static fn (InviteCodeUse $use): string => $use->getUsedBy(),
            $uses['beta']
        ));
    }
}
