<?php

declare(strict_types=1);

namespace App\Domain\Record;

use App\Domain\DomainException\DomainRecordNotFoundException;

class RecordNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Record not found.';
}
