<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AuthorizedClientNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Authorized client not found.';
}
