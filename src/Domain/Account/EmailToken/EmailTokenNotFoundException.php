<?php

declare(strict_types=1);

namespace App\Domain\Account\EmailToken;

use App\Domain\DomainException\DomainRecordNotFoundException;

class EmailTokenNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Email token not found.';
}
