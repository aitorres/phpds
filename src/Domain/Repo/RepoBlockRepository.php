<?php

declare(strict_types=1);

namespace App\Domain\Repo;

interface RepoBlockRepository
{
    /**
     * @throws RepoBlockNotFoundException
     */
    public function findByCid(string $cid): RepoBlock;

    /**
     * @return RepoBlock[]
     */
    public function findByRepoRev(string $repoRev): array;

    /**
     * @return RepoBlock[]
     */
    public function findAll(): array;

    public function save(RepoBlock $block): void;

    public function deleteByCid(string $cid): void;

    public function deleteAll(): void;
}
