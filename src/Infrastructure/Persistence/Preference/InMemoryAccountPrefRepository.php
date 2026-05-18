<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Preference;

use App\Domain\Preference\AccountPref;
use App\Domain\Preference\AccountPrefNotFoundException;
use App\Domain\Preference\AccountPrefRepository;

class InMemoryAccountPrefRepository implements AccountPrefRepository
{
    /** @var array<int, AccountPref> keyed by id */
    private array $prefs = [];

    private int $nextId = 1;

    /**
     * @param AccountPref[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $pref) {
            $this->prefs[$pref->getId()] = $pref;
            if ($pref->getId() >= $this->nextId) {
                $this->nextId = $pref->getId() + 1;
            }
        }
    }

    /**
     * @return AccountPref[]
     */
    public function findAll(): array
    {
        return array_values($this->prefs);
    }

    /**
     * @return AccountPref[]
     */
    public function findByName(string $name): array
    {
        return array_values(
            array_filter(
                $this->prefs,
                fn(AccountPref $p) => $p->getName() === $name,
            )
        );
    }

    public function findById(int $id): AccountPref
    {
        if (!isset($this->prefs[$id])) {
            throw new AccountPrefNotFoundException();
        }

        return $this->prefs[$id];
    }

    public function save(AccountPref $pref): AccountPref
    {
        $id = $pref->getId() === 0 ? $this->nextId++ : $pref->getId();

        $saved = new AccountPref(
            id: $id,
            name: $pref->getName(),
            valueJson: $pref->getValueJson(),
        );
        $this->prefs[$id] = $saved;

        return $saved;
    }

    public function deleteById(int $id): void
    {
        unset($this->prefs[$id]);
    }

    public function deleteByName(string $name): void
    {
        foreach ($this->prefs as $id => $pref) {
            if ($pref->getName() === $name) {
                unset($this->prefs[$id]);
            }
        }
    }

    public function deleteAll(): void
    {
        $this->prefs = [];
    }
}
