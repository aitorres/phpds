<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account;

use App\Application\Settings\Settings;
use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Infrastructure\Persistence\Account\InMemoryAccountRepository;
use Tests\TestCase;

class InMemoryAccountRepositoryTest extends TestCase
{
    public function testFindAllReturnsProvidedAccounts(): void
    {
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repository = new InMemoryAccountRepository(null, [$alice]);

        $this->assertEquals([$alice], $repository->findAll());
    }

    public function testFindAllSeedsFromSettings(): void
    {
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);
        $repository = new InMemoryAccountRepository($settings);

        $accounts = $repository->findAll();

        $this->assertCount(3, $accounts);
        $this->assertSame('did:web:alice.pds.test', $accounts[0]->getDid());
        $this->assertSame('alice@pds.test', $accounts[0]->getEmail());
    }

    public function testFindAccountByDid(): void
    {
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repository = new InMemoryAccountRepository(null, [$alice]);

        $this->assertEquals($alice, $repository->findAccountByDid('did:web:alice.pds.test'));
    }

    public function testFindAccountByDidThrowsWhenMissing(): void
    {
        $repository = new InMemoryAccountRepository(null, []);

        $this->expectException(AccountNotFoundException::class);
        $repository->findAccountByDid('did:web:missing.pds.test');
    }

    public function testFindAccountByEmailIsCaseInsensitive(): void
    {
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repository = new InMemoryAccountRepository(null, [$alice]);

        $this->assertEquals($alice, $repository->findAccountByEmail('  Alice@PDS.test  '));
    }

    public function testFindAccountByEmailThrowsWhenMissing(): void
    {
        $repository = new InMemoryAccountRepository(null, []);

        $this->expectException(AccountNotFoundException::class);
        $repository->findAccountByEmail('missing@pds.test');
    }

    public function testSeedDataMirrorsActorDids(): void
    {
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);
        $accountRepository = new InMemoryAccountRepository($settings);
        $actorRepository = new \App\Infrastructure\Persistence\Actor\InMemoryActorRepository($settings);

        $accountDids = array_map(
            static fn (Account $a): string => $a->getDid(),
            $accountRepository->findAll()
        );
        $actorDids = array_map(
            static fn (\App\Domain\Actor\Actor $a): string => $a->getDid(),
            $actorRepository->findAll()
        );

        $this->assertSame($actorDids, $accountDids);
    }
}
