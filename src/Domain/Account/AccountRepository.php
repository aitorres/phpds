<?php

declare(strict_types=1);

namespace App\Domain\Account;

interface AccountRepository
{
    /**
     * @return Account[]
     */
    public function findAll(): array;

    /**
     * @throws AccountNotFoundException
     */
    public function findAccountByHandle(string $handle): Account;

    /**
     * @throws AccountNotFoundException
     */
    public function findAccountByDid(string $did): Account;

    /**
     * @throws AccountNotFoundException
     */
    public function findAccountByEmail(string $email): Account;

    /**
     * Persist an account.
     */
    public function save(Account $account): void;
}
