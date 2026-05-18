<?php

declare(strict_types=1);

namespace App\Domain\Account\AppPassword;

use App\Domain\DomainException\DomainRecordNotFoundException;

class AppPasswordNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'App password not found.';
}
