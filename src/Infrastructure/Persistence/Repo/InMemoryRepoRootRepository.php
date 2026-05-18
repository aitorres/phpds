<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootNotFoundException;
use App\Domain\Repo\RepoRootRepository;

class InMemoryRepoRootRepository implements RepoRootRepository
{
    /** @var array<string, RepoRoot> keyed by did */
    private array $roots = [];

    /**
     * @param RepoRoot[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $root) {
            $this->roots[$root->getDid()] = $root;
        }
    }

    public function findByDid(string $did): RepoRoot
    {
        if (!isset($this->roots[$did])) {
            throw new RepoRootNotFoundException();
        }

        return $this->roots[$did];
    }

    public function upsert(RepoRoot $repoRoot): void
    {
        $this->roots[$repoRoot->getDid()] = $repoRoot;
    }

    public function deleteByDid(string $did): void
    {
        unset($this->roots[$did]);
    }
}
