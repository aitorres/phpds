<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account;

use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Account\SqliteAccountRepository;
use Tests\TestCase;

class SqliteAccountRepositoryTest extends TestCase
{
    private function newRepo(): SqliteAccountRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteAccountRepository($db);
    }

    public function testFindAllReturnsProvidedAccounts(): void
    {
        $repo = $this->newRepo();
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repo->save($alice);

        $this->assertEquals([$alice], $repo->findAll());
    }

    public function testFindAccountByDid(): void
    {
        $repo = $this->newRepo();
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repo->save($alice);

        $found = $repo->findAccountByDid('did:web:alice.pds.test');
        $this->assertSame('did:web:alice.pds.test', $found->getDid());
    }

    public function testFindAccountByDidThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(AccountNotFoundException::class);
        $repo->findAccountByDid('did:web:missing.pds.test');
    }

    public function testFindAccountByEmailIsCaseInsensitive(): void
    {
        $repo = $this->newRepo();
        $alice = new Account('did:web:alice.pds.test', 'alice@pds.test', 'hash');
        $repo->save($alice);

        $found = $repo->findAccountByEmail('  Alice@PDS.test  ');
        $this->assertSame('alice@pds.test', $found->getEmail());
    }

    public function testFindAccountByEmailThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(AccountNotFoundException::class);
        $repo->findAccountByEmail('missing@pds.test');
    }
}
