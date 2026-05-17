<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Settings\SettingsInterface;
use App\Domain\Pds\Atproto\Server\DescribeServerResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class DescribeServerAction extends PdsAction
{
    public function __construct(LoggerInterface $logger, SettingsInterface $settings)
    {
        parent::__construct($logger, $settings, 'com.atproto.server.describeServer');
    }

    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $pdsSettings = $this->settings->get('pds');
        $hostname = $pdsSettings['hostname'];

        return $this->respondWithData(new DescribeServerResponse(
            did: "did:web:$hostname",
            inviteCodeRequired: true,
            availableUserDomains: [".{$hostname}"],
            termsOfServiceUrl: $pdsSettings['termsOfServiceUrl'],
            privacyPolicyUrl: $pdsSettings['privacyPolicyUrl'],
            email: $pdsSettings['email'],
            phoneVerificationRequired: null
        ));
    }
}
