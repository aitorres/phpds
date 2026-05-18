<?php

declare(strict_types=1);

namespace App\Domain\Account\InviteCode;

use App\Domain\DomainException\DomainRecordNotFoundException;

class InviteCodeNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Invite code not found.';
}
