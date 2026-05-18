<?php

declare(strict_types=1);

namespace App\Domain\Record;

interface BacklinkRepository
{
    /**
     * @return Backlink[]
     */
    public function findByUri(string $uri): array;

    /**
     * @return Backlink[]
     */
    public function findByLinkTo(string $linkTo): array;

    public function save(Backlink $backlink): void;

    public function deleteByUri(string $uri): void;

    public function deleteAll(): void;
}
