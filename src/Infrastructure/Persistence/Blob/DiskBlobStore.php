<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Blob;

use App\Domain\Blob\BlobNotFoundException;
use App\Domain\Blob\BlobStore;
use Psr\Http\Message\StreamInterface;
use Slim\Psr7\Stream;

/**
 * Filesystem-backed blob store. Mirrors the reference TS DiskBlobstore.
 *
 * Layout under the configured root directory:
 *   temp/         — temporary uploads keyed by random hex
 *   blocks/<cid>  — committed permanent blobs
 *   quarantine/   — empty flag files (one per quarantined CID)
 *
 * Scoped per-actor by giving each ActorStore its own root directory.
 */
class DiskBlobStore implements BlobStore
{
    private readonly string $tempDir;

    private readonly string $permDir;

    private readonly string $quarantineDir;

    public function __construct(string $rootDir)
    {
        $this->tempDir       = rtrim($rootDir, '/') . '/temp';
        $this->permDir       = rtrim($rootDir, '/') . '/blocks';
        $this->quarantineDir = rtrim($rootDir, '/') . '/quarantine';

        foreach ([$this->tempDir, $this->permDir, $this->quarantineDir] as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0o755, true) && !is_dir($dir)) {
                    throw new \RuntimeException("Could not create blob directory: {$dir}");
                }
            }
        }
    }

    public function putTemp(string $bytes): string
    {
        $key = bin2hex(random_bytes(16));
        $path = $this->tempPath($key);

        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Could not write temp blob: {$path}");
        }

        return $key;
    }

    public function makePermanent(string $tempKey, string $cid): void
    {
        if (!$this->hasTemp($tempKey)) {
            throw new BlobNotFoundException();
        }

        $src = $this->tempPath($tempKey);
        $dst = $this->permPath($cid);
        if (!@rename($src, $dst)) {
            // Fallback if rename fails across volumes
            if (!copy($src, $dst)) {
                throw new \RuntimeException("Could not move temp blob to permanent: {$cid}");
            }
            @unlink($src);
        }
    }

    public function putPermanent(string $cid, string $bytes): void
    {
        if (file_put_contents($this->permPath($cid), $bytes) === false) {
            throw new \RuntimeException("Could not write permanent blob: {$cid}");
        }
    }

    public function hasTemp(string $tempKey): bool
    {
        return is_file($this->tempPath($tempKey));
    }

    public function hasStored(string $cid): bool
    {
        return is_file($this->permPath($cid));
    }

    public function getBytes(string $cid): string
    {
        if (!$this->hasStored($cid)) {
            throw new BlobNotFoundException();
        }

        $bytes = file_get_contents($this->permPath($cid));
        if ($bytes === false) {
            throw new \RuntimeException("Could not read permanent blob: {$cid}");
        }

        return $bytes;
    }

    public function getStream(string $cid): StreamInterface
    {
        if (!$this->hasStored($cid)) {
            throw new BlobNotFoundException();
        }

        $handle = fopen($this->permPath($cid), 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Could not open permanent blob stream: {$cid}");
        }

        return new Stream($handle);
    }

    public function delete(string $cid): void
    {
        if (!$this->hasStored($cid)) {
            throw new BlobNotFoundException();
        }

        @unlink($this->permPath($cid));
        @unlink($this->quarantinePath($cid));
    }

    /**
     * @param string[] $cids
     */
    public function deleteMany(array $cids): void
    {
        foreach ($cids as $cid) {
            if ($this->hasStored($cid)) {
                @unlink($this->permPath($cid));
            }
            @unlink($this->quarantinePath($cid));
        }
    }

    public function deleteTemp(string $tempKey): void
    {
        if (!$this->hasTemp($tempKey)) {
            throw new BlobNotFoundException();
        }

        @unlink($this->tempPath($tempKey));
    }

    public function quarantine(string $cid): void
    {
        if (!$this->hasStored($cid)) {
            throw new BlobNotFoundException();
        }

        @touch($this->quarantinePath($cid));
    }

    public function unquarantine(string $cid): void
    {
        if (!$this->hasStored($cid)) {
            throw new BlobNotFoundException();
        }

        @unlink($this->quarantinePath($cid));
    }

    public function isQuarantined(string $cid): bool
    {
        return is_file($this->quarantinePath($cid));
    }

    private function tempPath(string $key): string
    {
        return $this->tempDir . '/' . $this->safeName($key);
    }

    private function permPath(string $cid): string
    {
        return $this->permDir . '/' . $this->safeName($cid);
    }

    private function quarantinePath(string $cid): string
    {
        return $this->quarantineDir . '/' . $this->safeName($cid);
    }

    /**
     * Sanitises a key into a filesystem-safe filename.
     * Replaces anything not alphanumeric/hyphen/underscore.
     */
    private function safeName(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', $key) ?? $key;
    }
}
