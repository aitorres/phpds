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

    public function throwMissingKeyException(string $key): never
    {
        throw XrpcException::missingParam($this->actionName, $key);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function requireString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            $this->throwMissingKeyException($key);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function optionalString(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        if (!is_string($value)) {
            throw XrpcException::invalidParam($this->actionName, "{$key} must be a string", '');
        }

        return $value;
    }
}
