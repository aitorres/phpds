<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Preference;

use App\Domain\Preference\AccountPref;
use App\Domain\Preference\AccountPrefNotFoundException;
use App\Infrastructure\Persistence\Preference\InMemoryAccountPrefRepository;
use Tests\TestCase;

class InMemoryAccountPrefRepositoryTest extends TestCase
{
    private function makePref(
        string $name = 'muted-words',
        int $id = 0,
    ): AccountPref {
        return new AccountPref(id: $id, name: $name, valueJson: '[]');
    }

    public function testSaveAssignsAutoIncrementedId(): void
    {
        $repo = new InMemoryAccountPrefRepository();
        $saved = $repo->save($this->makePref());

        $this->assertSame(1, $saved->getId());
    }

    public function testFindById(): void
    {
        $repo = new InMemoryAccountPrefRepository();
        $saved = $repo->save($this->makePref());

        $result = $repo->findById($saved->getId());
        $this->assertSame($saved->getId(), $result->getId());
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryAccountPrefRepository();

        $this->expectException(AccountPrefNotFoundException::class);
        $repo->findById(999);
    }

    public function testFindByName(): void
    {
        $repo = new InMemoryAccountPrefRepository();
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'theme'));

        $results = $repo->findByName('muted-words');
        $this->assertCount(2, $results);
    }

    public function testDeleteByName(): void
    {
        $repo = new InMemoryAccountPrefRepository();
        $repo->save($this->makePref(name: 'muted-words'));
        $repo->save($this->makePref(name: 'theme'));
        $repo->deleteByName('muted-words');

        $this->assertEmpty($repo->findByName('muted-words'));
        $this->assertCount(1, $repo->findAll());
    }

    public function testDeleteAll(): void
    {
        $repo = new InMemoryAccountPrefRepository();
        $repo->save($this->makePref());
        $repo->save($this->makePref(name: 'foo'));
        $repo->deleteAll();

        $this->assertEmpty($repo->findAll());
    }
}
