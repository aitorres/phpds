<?php

declare(strict_types=1);

namespace App\Domain\Actor;

use App\Domain\DomainException\DomainRecordNotFoundException;

class ActorNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Actor not found';
}
