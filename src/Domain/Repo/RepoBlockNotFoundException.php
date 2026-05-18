<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use App\Domain\DomainException\DomainRecordNotFoundException;

class RepoBlockNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Repo block not found.';
}
