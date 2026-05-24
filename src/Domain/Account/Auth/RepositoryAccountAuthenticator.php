<?php

declare(strict_types=1);

namespace App\Domain\Account\Auth;

use App\Domain\Account\Account;
use App\Domain\Account\AccountNotFoundException;
use App\Domain\Account\AccountRepository;
use App\Domain\Account\AccountTakedownException;
use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordRepository;
use App\Domain\Account\Password\PasswordHasher;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use DateTimeImmutable;

/**
 * Default {@see AccountAuthenticator} that composes the account, app
 * password, and actor repositories with a {@see PasswordHasher}.
 *
 * Identifier resolution follows atproto conventions:
 * - `did:*` prefixes are looked up by DID,
 * - identifiers containing `@` are treated as email,
 * - everything else is treated as a handle.
 */
final class RepositoryAccountAuthenticator implements AccountAuthenticator
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly AppPasswordRepository $appPasswords,
        private readonly ActorRepository $actors,
        private readonly PasswordHasher $passwordHasher,
    ) {
    }

    public function login(string $identifier, string $password): AuthenticatedAccount
    {
        $account = $this->resolveAccount($identifier);
        if ($account === null) {
            throw new InvalidCredentialsException();
        }

        $appPassword = null;
        if (!$this->passwordHasher->verify($password, $account->getPasswordScrypt())) {
            $appPassword = $this->findMatchingAppPassword($account->getDid(), $password);
            if ($appPassword === null) {
                throw new InvalidCredentialsException();
            }
        }

        $actor = $this->resolveActorOrSynthesize($account);

        if ($actor->getTakedownRef() !== null) {
            throw new AccountTakedownException();
        }

        return new AuthenticatedAccount($account, $actor, $appPassword);
    }

    private function resolveAccount(string $identifier): ?Account
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        try {
            if (str_starts_with($identifier, 'did:')) {
                return $this->accounts->findAccountByDid($identifier);
            }

            if (str_contains($identifier, '@')) {
                return $this->accounts->findAccountByEmail($identifier);
            }

            return $this->accounts->findAccountByHandle($identifier);
        } catch (AccountNotFoundException) {
            return null;
        }
    }

    private function findMatchingAppPassword(string $did, string $password): ?AppPassword
    {
        foreach ($this->appPasswords->findAllForDid($did) as $candidate) {
            if ($this->passwordHasher->verify($password, $candidate->getPasswordScrypt())) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Build a synthetic, repo-inactive {@see Actor} when the account row
     * exists but no matching actor row does. That state shouldn't happen
     * in practice.
     */
    private function resolveActorOrSynthesize(Account $account): Actor
    {
        try {
            return $this->actors->findActorByDid($account->getDid());
        } catch (ActorNotFoundException) {
            return new Actor(
                did: $account->getDid(),
                handle: null,
                createdAt: new DateTimeImmutable(),
            );
        }
    }
}
