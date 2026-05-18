<?php

declare(strict_types=1);

namespace App\Domain\Repo;

interface RepoRootRepository
{
    /**
     * @throws RepoRootNotFoundException
     */
    public function findByDid(string $did): RepoRoot;

    public function upsert(RepoRoot $repoRoot): void;

    public function deleteByDid(string $did): void;
}
