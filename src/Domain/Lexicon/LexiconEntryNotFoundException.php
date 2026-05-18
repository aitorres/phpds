<?php

declare(strict_types=1);

namespace App\Domain\Lexicon;

use App\Domain\DomainException\DomainRecordNotFoundException;

class LexiconEntryNotFoundException extends DomainRecordNotFoundException
{
    public $message = 'Lexicon entry not found.';
}
