<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Identity;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Domain\Pds\Atproto\AppView\AppViewException;
use App\Domain\Pds\Atproto\Identity\ResolveHandleResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ResolveHandleAction extends PdsAction
{
    private ActorRepository $actorRepository;

    private AppViewClient $appViewClient;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        ActorRepository $actorRepository,
        AppViewClient $appViewClient
    ) {
        parent::__construct($logger, $settings, 'com.atproto.identity.resolveHandle');
        $this->actorRepository = $actorRepository;
        $this->appViewClient = $appViewClient;
    }

    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $params = $this->request->getQueryParams();
        $handleParam = $params['handle'] ?? null;

        if (!is_string($handleParam) || trim($handleParam) === '') {
            $this->throwMissingKeyException('handle');
        }

        $this->validateHandle($handleParam);

        // attempting to find the handle in our database
        try {
            $actor = $this->actorRepository->findActorByHandle($handleParam);
            return $this->respondWithData(new ResolveHandleResponse($actor->getDid()));
        } catch (ActorNotFoundException $e) {
            $pdsSettings = $this->settings->get('pds');
            $hostname = $pdsSettings['hostname'];
            if (str_ends_with($handleParam, ".{$hostname}")) {
                // we know for a fact that this handle should belong
                // to us, but we can't find it
                throw XrpcException::invalidRequest('Unable to resolve handle');
            }
        }

        // defer to the appview for handles we don't host
        try {
            $did = $this->appViewClient->resolveHandle($handleParam);
        } catch (AppViewException $e) {
            throw XrpcException::invalidRequest('Unable to resolve handle');
        }

        return $this->respondWithData(new ResolveHandleResponse($did));
    }

    private function validateHandle(string $handle): void
    {
        $errorCode = 'InvalidHandle';
        $length = strlen($handle);

        if ($length < 3) {
            throw XrpcException::invalidRequest('Handle too short', $errorCode);
        }

        if ($length > 18) {
            throw XrpcException::invalidRequest('Handle too long', $errorCode);
        }
    }
}
