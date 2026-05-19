<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Sync;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Pds\Atproto\Sync\GetLatestCommitResponse;
use App\Domain\Repo\RepoRootNotFoundException;
use Fig\Http\Message\StatusCodeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class GetLatestCommitAction extends PdsAction
{
    private ActorRepository $actorRepository;

    private ActorStoreFactory $actorStoreFactory;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        ActorRepository $actorRepository,
        ActorStoreFactory $actorStoreFactory
    ) {
        parent::__construct($logger, $settings, 'com.atproto.sync.getLatestCommit');
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
        $this->validateDid($did);

        try {
            $actor = $this->actorRepository->findActorByDid($did);
        } catch (ActorNotFoundException $e) {
            throw $this->namedError('RepoNotFound', sprintf('Could not find repo for DID: %s', $did));
        }

        if ($actor->getTakedownRef() !== null) {
            throw $this->namedError('RepoTakendown', sprintf('Repo has been taken down: %s', $did));
        }

        if ($actor->getDeactivatedAt() !== null) {
            throw $this->namedError('RepoDeactivated', sprintf('Repo has been deactivated: %s', $did));
        }

        try {
            $root = $this->actorStoreFactory->get($did)->getRepoRoot()->findByDid($did);
        } catch (RepoRootNotFoundException $e) {
            throw $this->namedError('RepoNotFound', sprintf('Could not find root for DID: %s', $did));
        }

        return $this->respondWithData(new GetLatestCommitResponse(
            cid: $root->getCid(),
            rev: $root->getRev(),
        ));
    }

    private function validateDid(string $did): void
    {
        if (!str_starts_with($did, 'did:')) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'Invalid DID',
                $did
            );
        }
    }

    private function namedError(string $error, string $message): XrpcException
    {
        return new XrpcException(
            $error,
            $message,
            StatusCodeInterface::STATUS_BAD_REQUEST
        );
    }
}
