<?php

declare(strict_types=1);

namespace App\Domain\Account;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AccountNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Account not found';
}
