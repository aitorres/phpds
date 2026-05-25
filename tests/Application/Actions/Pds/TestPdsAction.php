<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

final class TestPdsAction extends PdsAction
{
    public function __construct(LoggerInterface $logger, Settings $settings)
    {
        parent::__construct($logger, $settings, 'com.atproto.server.testAction');
    }

    protected function action(): Response
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function exposeRequireString(array $body, string $key): string
    {
        return $this->requireString($body, $key);
    }

    /**
     * @param array<string, mixed> $body
     */
    public function exposeOptionalString(array $body, string $key): ?string
    {
        return $this->optionalString($body, $key);
    }
}
