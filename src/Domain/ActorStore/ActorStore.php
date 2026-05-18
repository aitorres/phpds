<?php

declare(strict_types=1);

namespace App\Domain\ActorStore;

use App\Domain\Blob\BlobRepository;
use App\Domain\Blob\BlobStore;
use App\Domain\Preference\AccountPrefRepository;
use App\Domain\Record\BacklinkRepository;
use App\Domain\Record\RecordBlobRepository;
use App\Domain\Record\RecordRepository;
use App\Domain\Repo\RepoBlockRepository;
use App\Domain\Repo\RepoRootRepository;

/**
 * Encapsulates all per-actor data stores for a single DID.
 *
 * Mirrors the reference TS actor-store, which uses one SQLite database per DID.
 * Each ActorStore instance is exclusively scoped to $did; and the contained
 * repositories are isolated by the ActorStore's DID.
 */
class ActorStore
{
    public function __construct(
        private readonly string $did,
        private readonly RecordRepository $records,
        private readonly RecordBlobRepository $recordBlobs,
        private readonly BacklinkRepository $backlinks,
        private readonly BlobRepository $blobs,
        private readonly BlobStore $blobStore,
        private readonly RepoBlockRepository $repoBlocks,
        private readonly AccountPrefRepository $prefs,
        private readonly RepoRootRepository $repoRoot,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getRecords(): RecordRepository
    {
        return $this->records;
    }

    public function getRecordBlobs(): RecordBlobRepository
    {
        return $this->recordBlobs;
    }

    public function getBacklinks(): BacklinkRepository
    {
        return $this->backlinks;
    }

    public function getBlobs(): BlobRepository
    {
        return $this->blobs;
    }

    public function getBlobStore(): BlobStore
    {
        return $this->blobStore;
    }

    public function getRepoBlocks(): RepoBlockRepository
    {
        return $this->repoBlocks;
    }

    public function getPrefs(): AccountPrefRepository
    {
        return $this->prefs;
    }

    public function getRepoRoot(): RepoRootRepository
    {
        return $this->repoRoot;
    }
}
