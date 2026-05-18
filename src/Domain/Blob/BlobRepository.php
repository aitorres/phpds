<?php

declare(strict_types=1);

namespace App\Domain\Blob;

interface BlobRepository
{
    /**
     * @throws BlobNotFoundException
     */
    public function findByCid(string $cid): Blob;

    /**
     * @return Blob[]
     */
    public function findAll(): array;

    /**
     * @return Blob[]  All blobs whose tempKey is not null (awaiting commit).
     */
    public function findTemporary(): array;

    public function save(Blob $blob): void;

    public function deleteByCid(string $cid): void;

    public function deleteAll(): void;
}
