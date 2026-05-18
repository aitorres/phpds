<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\DomainException\DomainRecordNotFoundException;

class DeviceNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Device not found.';
}
