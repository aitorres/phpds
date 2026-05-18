<?php

declare(strict_types=1);

namespace App\Domain\Account\RefreshToken;

use App\Domain\DomainException\DomainRecordNotFoundException;

class RefreshTokenNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Refresh token not found.';
}
