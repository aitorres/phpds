<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\ActorStore;

use App\Domain\ActorStore\ActorStore;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\ActorStoreSchema;
use App\Infrastructure\Persistence\Blob\DiskBlobStore;
use App\Infrastructure\Persistence\Blob\SqliteBlobRepository;
use App\Infrastructure\Persistence\Preference\SqliteAccountPrefRepository;
use App\Infrastructure\Persistence\Record\SqliteBacklinkRepository;
use App\Infrastructure\Persistence\Record\SqliteRecordBlobRepository;
use App\Infrastructure\Persistence\Record\SqliteRecordRepository;
use App\Infrastructure\Persistence\Repo\SqliteRepoBlockRepository;
use App\Infrastructure\Persistence\Repo\SqliteRepoRootRepository;

/**
 * SQLite-backed actor-store factory.
 *
 * Each DID gets its own SQLite database file named after the SHA-256 of
 * the DID. Blob bytes for each DID live under a sibling directory.
 */
class SqliteActorStoreFactory implements ActorStoreFactory
{
    /** @var array<string, ActorStore> keyed by DID */
    private array $cache = [];

    public function __construct(
        private readonly string $actorsDirectory,
        private readonly string $blobsDirectory,
    ) {
    }

    public function get(string $did): ActorStore
    {
        if (isset($this->cache[$did])) {
            return $this->cache[$did];
        }

        $db = new Database($this->dbLocation($did));
        ActorStoreSchema::apply($db);

        $blobStore = new DiskBlobStore($this->blobDir($did));

        return $this->cache[$did] = new ActorStore(
            did: $did,
            records: new SqliteRecordRepository($db),
            recordBlobs: new SqliteRecordBlobRepository($db),
            backlinks: new SqliteBacklinkRepository($db),
            blobs: new SqliteBlobRepository($db),
            blobStore: $blobStore,
            repoBlocks: new SqliteRepoBlockRepository($db),
            prefs: new SqliteAccountPrefRepository($db),
            repoRoot: new SqliteRepoRootRepository($db),
        );
    }

    public function destroy(string $did): void
    {
        unset($this->cache[$did]);

        $loc = $this->dbLocation($did);
        if ($loc !== ':memory:' && is_file($loc)) {
            @unlink($loc);
            @unlink($loc . '-wal');
            @unlink($loc . '-shm');
        }

        $blobDir = $this->blobDir($did);
        if (is_dir($blobDir)) {
            self::removeDirectory($blobDir);
        }
    }

    private function dbLocation(string $did): string
    {
        if ($this->actorsDirectory === ':memory:') {
            return ':memory:';
        }

        return rtrim($this->actorsDirectory, '/') . '/' . $this->safeId($did) . '.sqlite';
    }

    private function blobDir(string $did): string
    {
        return rtrim($this->blobsDirectory, '/') . '/' . $this->safeId($did);
    }

    private function safeId(string $did): string
    {
        return hash('sha256', $did);
    }

    private static function removeDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            assert($file instanceof \SplFileInfo);
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($dir);
    }
}
