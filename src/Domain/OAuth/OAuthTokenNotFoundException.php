<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\DomainException\DomainRecordNotFoundException;

class OAuthTokenNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'OAuth token not found.';
}
