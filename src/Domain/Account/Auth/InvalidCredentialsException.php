<?php

declare(strict_types=1);

namespace App\Domain\Account\Auth;

use App\Domain\DomainException\DomainException;

/**
 * Thrown by {@see AccountAuthenticator::login()} when the supplied
 * identifier/password pair does not match any account or app password.
 */
class InvalidCredentialsException extends DomainException
{
    public $message = 'Invalid identifier or password';
}
