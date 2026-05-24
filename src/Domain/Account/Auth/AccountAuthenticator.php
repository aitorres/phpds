<?php

declare(strict_types=1);

namespace App\Domain\Account\Auth;

use App\Domain\Account\AccountTakedownException;

/**
 * Domain service that authenticates an account from a free-form identifier
 * (DID, email, or handle) and a plaintext password.
 */
interface AccountAuthenticator
{
    /**
     * @throws InvalidCredentialsException When the identifier does not
     *         resolve, or the password does not match any credential.
     * @throws AccountTakedownException    When the resolved actor is
     *         taken down. Callers that explicitly want to allow takendown
     *         accounts must catch and recover from this.
     */
    public function login(string $identifier, string $password): AuthenticatedAccount;
}
