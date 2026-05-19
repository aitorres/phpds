<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Sync;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Pds\Atproto\Sync\ListReposResponse;
use App\Domain\Pds\Atproto\Sync\RepoView;
use App\Domain\Repo\RepoRootNotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class ListReposAction extends PdsAction
{
    public const DEFAULT_LIMIT = 500;

    public const MAX_LIMIT = 1000;

    private ActorRepository $actorRepository;

    private ActorStoreFactory $actorStoreFactory;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        ActorRepository $actorRepository,
        ActorStoreFactory $actorStoreFactory
    ) {
        parent::__construct($logger, $settings, 'com.atproto.sync.listRepos');
        $this->actorRepository = $actorRepository;
        $this->actorStoreFactory = $actorStoreFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $params = $this->request->getQueryParams();

        $limit = $this->parseLimit($params['limit'] ?? null);
        $cursor = $this->parseCursor($params['cursor'] ?? null);

        $actors = $this->actorRepository->findPage($cursor, $limit);

        $repos = [];
        foreach ($actors as $actor) {
            try {
                $root = $this->actorStoreFactory->get($actor->getDid())
                    ->getRepoRoot()
                    ->findByDid($actor->getDid());
            } catch (RepoRootNotFoundException $e) {
                // actor exists but its repo hasn't been initialised; skip it
                continue;
            }

            $status = $this->deriveStatus($actor);
            $repos[] = new RepoView(
                did: $actor->getDid(),
                head: $root->getCid(),
                rev: $root->getRev(),
                active: $status === null,
                status: $status,
            );
        }

        // a full page means there may be more results; the cursor is the
        // last actor DID returned (whether or not it produced a RepoView)
        $nextCursor = null;
        if (count($actors) === $limit) {
            $last = end($actors);
            assert($last !== false);
            $nextCursor = $last->getDid();
        }

        return $this->respondWithData(new ListReposResponse($repos, $nextCursor));
    }

    private function parseLimit(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_LIMIT;
        }

        if (!is_string($raw) && !is_int($raw)) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'limit must be an integer',
                (string) (is_scalar($raw) ? $raw : '')
            );
        }

        if (is_string($raw) && !preg_match('/^-?\d+$/', $raw)) {
            throw XrpcException::invalidParam($this->actionName, 'limit must be an integer', $raw);
        }

        $limit = (int) $raw;
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw XrpcException::invalidParam(
                $this->actionName,
                sprintf('limit must be between 1 and %d', self::MAX_LIMIT),
                (string) $raw
            );
        }

        return $limit;
    }

    private function parseCursor(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (!is_string($raw)) {
            throw XrpcException::invalidParam($this->actionName, 'cursor must be a string', '');
        }

        $cursor = trim($raw);
        return $cursor === '' ? null : $cursor;
    }

    /**
     * Derive the lex `status` for an actor's repo view.
     *
     * Returns null when the repo is active, and otherwise
     * returns a string indicating a non-active repo status
     * (e.g. "takendown" or "deactivated").
     */
    private function deriveStatus(\App\Domain\Actor\Actor $actor): ?string
    {
        if ($actor->getTakedownRef() !== null) {
            return RepoView::STATUS_TAKENDOWN;
        }

        if ($actor->getDeactivatedAt() !== null) {
            return RepoView::STATUS_DEACTIVATED;
        }

        return null;
    }
}
