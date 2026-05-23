<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Sync;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Did\Did;
use App\Domain\Pds\Atproto\Sync\GetRepoStatusResponse;
use App\Domain\Repo\RepoRootNotFoundException;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class GetRepoStatusAction extends PdsAction
{
    private ActorRepository $actorRepository;

    private ActorStoreFactory $actorStoreFactory;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        ActorRepository $actorRepository,
        ActorStoreFactory $actorStoreFactory
    ) {
        parent::__construct($logger, $settings, 'com.atproto.sync.getRepoStatus');
        $this->actorRepository = $actorRepository;
        $this->actorStoreFactory = $actorStoreFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $params = $this->request->getQueryParams();
        $didParam = $params['did'] ?? null;

        if (!is_string($didParam) || trim($didParam) === '') {
            $this->throwMissingKeyException('did');
        }

        $did = trim($didParam);
        if (!Did::isValid($did)) {
            throw XrpcException::invalidParam($this->actionName, 'Invalid DID', $did);
        }

        try {
            $actor = $this->actorRepository->findActorByDid($did);
        } catch (ActorNotFoundException $e) {
            throw new XrpcException(
                'RepoNotFound',
                sprintf('Could not find repo for DID: %s', $did),
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $status = $actor->getRepoStatus();
        $active = $status === null;

        $rev = null;
        if ($active) {
            try {
                $root = $this->actorStoreFactory->get($did)->getRepoRoot()->findByDid($did);
                $rev = $root->getRev();
            } catch (RepoRootNotFoundException $e) {
                // active actor without an initialised repo
                $rev = null;
            }
        }

        return $this->respondWithData(new GetRepoStatusResponse(
            did: $did,
            active: $active,
            status: $status,
            rev: $rev,
        ));
    }
}
