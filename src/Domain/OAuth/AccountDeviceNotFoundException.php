<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AccountDeviceNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Account device not found.';
}
