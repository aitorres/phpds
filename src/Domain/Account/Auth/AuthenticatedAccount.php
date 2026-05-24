<?php

declare(strict_types=1);

namespace App\Domain\Account\Auth;

use App\Domain\Account\Account;
use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Actor\Actor;

/**
 * Outcome of a successful credential check by {@see AccountAuthenticator}.
 */
final class AuthenticatedAccount
{
    public function __construct(
        public readonly Account $account,
        public readonly Actor $actor,
        public readonly ?AppPassword $appPassword,
    ) {
    }

    /**
     * True when authentication used an app password rather than the
     * account's main password.
     */
    public function isAppPasswordAuth(): bool
    {
        return $this->appPassword !== null;
    }
}
