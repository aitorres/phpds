<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account;

use App\Application\Settings\SettingsInterface;
use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;

class InMemoryAccountRepository implements AccountRepository
{
    /**
     * @var array<string, Account>
     */
    private array $accountsByDid;

    /**
     * @param Account[]|null $accounts
     */
    public function __construct(?SettingsInterface $settings = null, ?array $accounts = null)
    {
        if ($accounts === null) {
            $hostname = $settings !== null ? ($settings->get('pds')['hostname'] ?? 'localhost') : 'localhost';
            $accounts = [
                new Account(
                    did: "did:web:alice.{$hostname}",
                    email: "alice@{$hostname}",
                    passwordScrypt: 'placeholder-scrypt-hash',
                    emailConfirmedAt: new \DateTimeImmutable('2024-01-01T00:00:00Z'),
                    invitesDisabled: false,
                ),
                new Account(
                    did: "did:web:bob.{$hostname}",
                    email: "bob@{$hostname}",
                    passwordScrypt: 'placeholder-scrypt-hash',
                    emailConfirmedAt: null,
                    invitesDisabled: false,
                ),
                new Account(
                    did: 'did:plc:carol000000000000000000000',
                    email: "carol@{$hostname}",
                    passwordScrypt: 'placeholder-scrypt-hash',
                    emailConfirmedAt: new \DateTimeImmutable('2024-01-03T00:00:00Z'),
                    invitesDisabled: true,
                ),
            ];
        }

        $this->accountsByDid = [];
        foreach ($accounts as $account) {
            $this->accountsByDid[$account->getDid()] = $account;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        return array_values($this->accountsByDid);
    }

    /**
     * {@inheritDoc}
     */
    public function findAccountByHandle(string $handle): Account
    {
        // Not implemented yet
        throw new AccountNotFoundException();
    }

    /**
     * {@inheritdoc}
     */
    public function findAccountByDid(string $did): Account
    {
        $key = trim($did);

        if (!isset($this->accountsByDid[$key])) {
            throw new AccountNotFoundException();
        }

        return $this->accountsByDid[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function findAccountByEmail(string $email): Account
    {
        $needle = strtolower(trim($email));

        if ($needle === '') {
            throw new AccountNotFoundException();
        }

        foreach ($this->accountsByDid as $account) {
            if ($account->getEmail() === $needle) {
                return $account;
            }
        }

        throw new AccountNotFoundException();
    }
}
