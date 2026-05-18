<?php

declare(strict_types=1);

namespace App\Domain\Blob;

use App\Domain\DomainException\DomainRecordNotFoundException;

class BlobNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Blob not found.';
}
