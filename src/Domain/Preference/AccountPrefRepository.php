<?php

declare(strict_types=1);

namespace App\Domain\Preference;

interface AccountPrefRepository
{
    /**
     * @return AccountPref[]
     */
    public function findAll(): array;

    /**
     * @return AccountPref[]
     */
    public function findByName(string $name): array;

    /**
     * @throws AccountPrefNotFoundException
     */
    public function findById(int $id): AccountPref;

    /**
     * Persist a preference. If the $pref has id = 0 (unsaved), an auto-
     * incremented id is assigned by the repository implementation.
     */
    public function save(AccountPref $pref): AccountPref;

    public function deleteById(int $id): void;

    public function deleteByName(string $name): void;

    public function deleteAll(): void;
}
