<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\PdsAction;
use App\Domain\Pds\Atproto\Server\DescribeServerResponse;
use Psr\Http\Message\ResponseInterface as Response;

class DescribeServerAction extends PdsAction
{
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
