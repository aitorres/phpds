<?php

declare(strict_types=1);

namespace App\Infrastructure\Atproto\AppView;

use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Domain\Pds\Atproto\AppView\AppViewException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class HttpAppViewClient implements AppViewClient
{
    private ClientInterface $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function resolveHandle(string $handle): string
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                'xrpc/com.atproto.identity.resolveHandle',
                ['query' => ['handle' => $handle]]
            );
        } catch (GuzzleException $e) {
            throw new AppViewException(
                sprintf('AppView resolveHandle request failed: %s', $e->getMessage()),
                0,
                $e
            );
        }

        $payload = json_decode((string) $response->getBody(), true);

        if (!is_array($payload) || !isset($payload['did']) || !is_string($payload['did'])) {
            throw new AppViewException('AppView resolveHandle response is missing a string `did`.');
        }

        return $payload['did'];
    }
}
