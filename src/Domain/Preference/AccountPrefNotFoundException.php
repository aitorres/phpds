<?php

declare(strict_types=1);

namespace App\Domain\Preference;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AccountPrefNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Account preference not found.';
}
