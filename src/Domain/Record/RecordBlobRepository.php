<?php

declare(strict_types=1);

namespace App\Domain\Record;

interface RecordBlobRepository
{
    /**
     * @return RecordBlob[]
     */
    public function findByBlobCid(string $blobCid): array;

    /**
     * @return RecordBlob[]
     */
    public function findByRecordUri(string $recordUri): array;

    public function save(RecordBlob $recordBlob): void;

    public function deleteByRecordUri(string $recordUri): void;

    public function deleteByBlobCid(string $blobCid): void;
}
