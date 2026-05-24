<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\DomainException\DomainException;

/**
 * Raised when an authenticated request resolves an account whose actor is
 * currently subject to a takedown and the caller did not explicitly opt
 * in to operating on takendown accounts.
 */
class AccountTakedownException extends DomainException
{
    public $message = 'Account has been taken down';
}
