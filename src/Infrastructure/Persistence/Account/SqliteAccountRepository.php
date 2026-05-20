<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account;

use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;
use App\Domain\Common\StringNormalizer;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteAccountRepository implements AccountRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return Account[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM account ORDER BY did');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function findAccountByHandle(string $handle): Account
    {
        $handle = StringNormalizer::normalizeHandle($handle) ?? '';
        if ($handle === '') {
            throw new AccountNotFoundException();
        }

        $row = $this->db->fetchOne(
            'SELECT a.* FROM account a JOIN actor ac ON ac.did = a.did WHERE ac.handle = ?',
            [$handle]
        );

        if ($row === null) {
            throw new AccountNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findAccountByDid(string $did): Account
    {
        $row = $this->db->fetchOne('SELECT * FROM account WHERE did = ?', [trim($did)]);

        if ($row === null) {
            throw new AccountNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findAccountByEmail(string $email): Account
    {
        $email = StringNormalizer::normalizeEmail($email);
        if ($email === '') {
            throw new AccountNotFoundException();
        }

        $row = $this->db->fetchOne('SELECT * FROM account WHERE email = ?', [$email]);

        if ($row === null) {
            throw new AccountNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(Account $account): void
    {
        $this->db->execute(
            'INSERT INTO account (did, email, password_scrypt, email_confirmed_at, invites_disabled)
             VALUES (:did, :email, :password_scrypt, :email_confirmed_at, :invites_disabled)
             ON CONFLICT(did) DO UPDATE SET
                email = excluded.email,
                password_scrypt = excluded.password_scrypt,
                email_confirmed_at = excluded.email_confirmed_at,
                invites_disabled = excluded.invites_disabled',
            [
                'did'                => $account->getDid(),
                'email'              => $account->getEmail(),
                'password_scrypt'    => $account->getPasswordScrypt(),
                'email_confirmed_at' => $account->getEmailConfirmedAt()?->format(DATE_ATOM),
                'invites_disabled'   => $account->isInvitesDisabled() ? 1 : 0,
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Account
    {
        $emailConfirmedAt = Row::nstr($row, 'email_confirmed_at');

        return new Account(
            did: Row::str($row, 'did'),
            email: Row::str($row, 'email'),
            passwordScrypt: Row::str($row, 'password_scrypt'),
            emailConfirmedAt: $emailConfirmedAt === null
                ? null
                : new DateTimeImmutable($emailConfirmedAt),
            invitesDisabled: Row::bool($row, 'invites_disabled'),
        );
    }
}
