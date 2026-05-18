<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\ActorStore;

use App\Domain\ActorStore\ActorStore;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Infrastructure\Persistence\Blob\InMemoryBlobRepository;
use App\Infrastructure\Persistence\Blob\InMemoryBlobStore;
use App\Infrastructure\Persistence\Preference\InMemoryAccountPrefRepository;
use App\Infrastructure\Persistence\Record\InMemoryBacklinkRepository;
use App\Infrastructure\Persistence\Record\InMemoryRecordBlobRepository;
use App\Infrastructure\Persistence\Record\InMemoryRecordRepository;
use App\Infrastructure\Persistence\Repo\InMemoryRepoBlockRepository;
use App\Infrastructure\Persistence\Repo\InMemoryRepoRootRepository;

/**
 * In-memory implementation of ActorStoreFactory.
 *
 * Lazily creates and caches a full set of in-memory repositories for each DID.
 * Destroying a store wipes all state for that DID (e.g. on account deletion).
 */
class InMemoryActorStoreFactory implements ActorStoreFactory
{
    /** @var array<string, ActorStore> keyed by DID */
    private array $stores = [];

    public function get(string $did): ActorStore
    {
        if (!isset($this->stores[$did])) {
            $this->stores[$did] = new ActorStore(
                did: $did,
                records: new InMemoryRecordRepository(),
                recordBlobs: new InMemoryRecordBlobRepository(),
                backlinks: new InMemoryBacklinkRepository(),
                blobs: new InMemoryBlobRepository(),
                blobStore: new InMemoryBlobStore(),
                repoBlocks: new InMemoryRepoBlockRepository(),
                prefs: new InMemoryAccountPrefRepository(),
                repoRoot: new InMemoryRepoRootRepository(),
            );
        }

        return $this->stores[$did];
    }

    public function destroy(string $did): void
    {
        unset($this->stores[$did]);
    }
}
