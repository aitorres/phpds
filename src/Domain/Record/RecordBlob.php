<?php

declare(strict_types=1);

namespace App\Domain\Record;

use JsonSerializable;

/**
 * Join table linking a blob CID to the record that references it.
 * Maps to the `record_blob` table in the actor-store schema.
 *
 * Lives inside an ActorStore scoped to a specific DID, so no `did` field here.
 */
class RecordBlob implements JsonSerializable
{
    public function __construct(
        private readonly string $blobCid,
        private readonly string $recordUri,
    ) {
    }

    public function getBlobCid(): string
    {
        return $this->blobCid;
    }

    public function getRecordUri(): string
    {
        return $this->recordUri;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'blobCid'   => $this->blobCid,
            'recordUri' => $this->recordUri,
        ];
    }
}
