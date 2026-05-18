<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repo;

use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockNotFoundException;
use App\Domain\Repo\RepoBlockRepository;

class InMemoryRepoBlockRepository implements RepoBlockRepository
{
    /** @var array<string, RepoBlock> keyed by cid */
    private array $blocks = [];

    /**
     * @param RepoBlock[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $block) {
            $this->blocks[$block->getCid()] = $block;
        }
    }

    public function findByCid(string $cid): RepoBlock
    {
        if (!isset($this->blocks[$cid])) {
            throw new RepoBlockNotFoundException();
        }

        return $this->blocks[$cid];
    }

    /**
     * @return RepoBlock[]
     */
    public function findByRepoRev(string $repoRev): array
    {
        return array_values(
            array_filter(
                $this->blocks,
                fn(RepoBlock $b) => $b->getRepoRev() === $repoRev,
            )
        );
    }

    /**
     * @return RepoBlock[]
     */
    public function findAll(): array
    {
        return array_values($this->blocks);
    }

    public function save(RepoBlock $block): void
    {
        $this->blocks[$block->getCid()] = $block;
    }

    public function deleteByCid(string $cid): void
    {
        unset($this->blocks[$cid]);
    }

    public function deleteAll(): void
    {
        $this->blocks = [];
    }
}
