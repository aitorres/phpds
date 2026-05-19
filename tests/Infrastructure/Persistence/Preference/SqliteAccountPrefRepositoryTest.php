<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Preference;

use App\Domain\Preference\AccountPref;
use App\Domain\Preference\AccountPrefNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Preference\SqliteAccountPrefRepository;
use Tests\TestCase;

class SqliteAccountPrefRepositoryTest extends TestCase
{
    private function newRepo(): SqliteAccountPrefRepository
    {
        $db = new Database(':memory:');
        ActorStoreSchema::apply($db);

        return new SqliteAccountPrefRepository($db);
    }

    private function makePref(
        string $name = 'muted-words',
        int $id = 0,
    ): AccountPref {
        return new AccountPref(id: $id, name: $name, valueJson: '[]');
    }

    public function testSaveAssignsAutoIncrementedId(): void
    {
        $repo = $this->newRepo();
        $saved = $repo->save($this->makePref());

        $this->assertSame(1, $saved->getId());
    }

    public function testFindById(): void
    {
        $repo = $this->newRepo();
        $saved = $repo->save($this->makePref());

        $result = $repo->findById($saved->getId());
        $this->assertSame($saved->getId(), $result->getId());
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(AccountPrefNotFoundException::class);
        $repo->findById(999);
    }

    public function testFindByName(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'theme'));

        $results = $repo->findByName('muted-words');
        $this->assertCount(2, $results);
    }

    public function testDeleteByName(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'theme'));
        $repo->deleteByName('muted-words');

        $this->assertEmpty($repo->findByName('muted-words'));
        $this->assertCount(1, $repo->findAll());
    }

    public function testDeleteAll(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makePref());
        $repo->save($this->makePref(name: 'foo'));
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
