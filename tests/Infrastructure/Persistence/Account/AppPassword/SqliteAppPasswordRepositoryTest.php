<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\AppPassword;

use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\Account\AppPassword\SqliteAppPasswordRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteAppPasswordRepositoryTest extends TestCase
{
    private function newRepo(): SqliteAppPasswordRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteAppPasswordRepository($db);
    }

    private function makePassword(string $did = 'did:plc:alice', string $name = 'main'): AppPassword
    {
        return new AppPassword(
            did: $did,
            name: $name,
            passwordScrypt: 'hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindAllForDid(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makePassword(name: 'main'));
        $repo->save($this->makePassword(name: 'ci'));

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
    }

    public function testFindByDidAndName(): void
    {
        $repo = $this->newRepo();
        $p = $this->makePassword();
        $repo->save($p);

        $found = $repo->findByDidAndName('did:plc:alice', 'main');
        $this->assertSame('main', $found->getName());
    }

    public function testFindThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(AppPasswordNotFoundException::class);
        $repo->findByDidAndName('did:plc:alice', 'nope');
    }

    public function testDeleteByDidAndName(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makePassword());
        $repo->deleteByDidAndName('did:plc:alice', 'main');

        $this->assertEmpty($repo->findAllForDid('did:plc:alice'));
    }
}
