<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Record;

use App\Domain\Record\RecordBlob;
use App\Domain\Record\RecordBlobRepository;

class InMemoryRecordBlobRepository implements RecordBlobRepository
{
    /** @var RecordBlob[] */
    private array $entries = [];

    /**
     * @param RecordBlob[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        $this->entries = $seeds;
    }

    /**
     * @return RecordBlob[]
     */
    public function findByBlobCid(string $blobCid): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(RecordBlob $rb) => $rb->getBlobCid() === $blobCid,
            )
        );
    }

    /**
     * @return RecordBlob[]
     */
    public function findByRecordUri(string $recordUri): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(RecordBlob $rb) => $rb->getRecordUri() === $recordUri,
            )
        );
    }

    public function save(RecordBlob $recordBlob): void
    {
        foreach ($this->entries as $existing) {
            if (
                $existing->getBlobCid() === $recordBlob->getBlobCid()
                && $existing->getRecordUri() === $recordBlob->getRecordUri()
            ) {
                return;
            }
        }
        $this->entries[] = $recordBlob;
    }

    public function deleteByRecordUri(string $recordUri): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(RecordBlob $rb) => $rb->getRecordUri() !== $recordUri,
            )
        );
    }

    public function deleteByBlobCid(string $blobCid): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(RecordBlob $rb) => $rb->getBlobCid() !== $blobCid,
            )
        );
    }
}
