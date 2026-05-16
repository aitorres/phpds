<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds;

use App\Application\Actions\Action;
use App\Application\Settings\SettingsInterface;
use Psr\Log\LoggerInterface;

abstract class PdsAction extends Action
{
    protected SettingsInterface $settings;

    public function __construct(LoggerInterface $logger, SettingsInterface $settings)
    {
        parent::__construct($logger);
        $this->settings = $settings;
    }
}
