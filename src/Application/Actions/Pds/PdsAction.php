<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds;

use App\Application\Actions\Action;
use App\Application\Settings\SettingsInterface;
use Psr\Log\LoggerInterface;

abstract class PdsAction extends Action
{
    protected SettingsInterface $settings;

    public string $actionName;

    public function __construct(LoggerInterface $logger, SettingsInterface $settings, string $actionName)
    {
        parent::__construct($logger);
        $this->settings = $settings;
        $this->actionName = $actionName;
    }

    public function throwMissingKeyException(string $key): void
    {
        throw XrpcException::missingParam($this->actionName, $key);
    }
}
