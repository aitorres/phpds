<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use App\Domain\DomainException\DomainRecordNotFoundException;

class RepoRootNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Repo root not found.';
}
