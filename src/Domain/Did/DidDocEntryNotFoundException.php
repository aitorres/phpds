<?php

declare(strict_types=1);

namespace App\Domain\Did;

use App\Domain\DomainException\DomainRecordNotFoundException;

class DidDocEntryNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'DID document cache entry not found.';
}
