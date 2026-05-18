<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AuthorizationRequestNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Authorization request not found.';
}
