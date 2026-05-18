<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Blob;

use App\Domain\Blob\Blob;
use App\Domain\Blob\BlobNotFoundException;
use App\Domain\Blob\BlobRepository;

class InMemoryBlobRepository implements BlobRepository
{
    /** @var array<string, Blob> keyed by cid */
    private array $blobs = [];

    /**
     * @param Blob[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $blob) {
            $this->blobs[$blob->getCid()] = $blob;
        }
    }

    public function findByCid(string $cid): Blob
    {
        if (!isset($this->blobs[$cid])) {
            throw new BlobNotFoundException();
        }

        return $this->blobs[$cid];
    }

    /**
     * @return Blob[]
     */
    public function findAll(): array
    {
        return array_values($this->blobs);
    }

    /**
     * @return Blob[]
     */
    public function findTemporary(): array
    {
        return array_values(
            array_filter(
                $this->blobs,
                fn(Blob $b) => $b->getTempKey() !== null,
            )
        );
    }

    public function save(Blob $blob): void
    {
        $this->blobs[$blob->getCid()] = $blob;
    }

    public function deleteByCid(string $cid): void
    {
        unset($this->blobs[$cid]);
    }

    public function deleteAll(): void
    {
        $this->blobs = [];
    }
}
