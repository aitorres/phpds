<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Blob;

use App\Domain\Blob\BlobNotFoundException;
use App\Domain\Blob\BlobStore;
use Slim\Psr7\Stream;
use Psr\Http\Message\StreamInterface;

class InMemoryBlobStore implements BlobStore
{
    /** @var array<string, string> tempKey => raw bytes */
    private array $temp = [];

    /** @var array<string, string> cid => raw bytes */
    private array $perm = [];

    /** @var array<string, true> set of quarantined CIDs */
    private array $quarantined = [];

    public function putTemp(string $bytes): string
    {
        $key = bin2hex(random_bytes(16));
        $this->temp[$key] = $bytes;

        return $key;
    }

    public function makePermanent(string $tempKey, string $cid): void
    {
        if (!isset($this->temp[$tempKey])) {
            throw new BlobNotFoundException();
        }

        $this->perm[$cid] = $this->temp[$tempKey];
        unset($this->temp[$tempKey]);
    }

    public function putPermanent(string $cid, string $bytes): void
    {
        $this->perm[$cid] = $bytes;
    }

    public function hasTemp(string $tempKey): bool
    {
        return isset($this->temp[$tempKey]);
    }

    public function hasStored(string $cid): bool
    {
        return isset($this->perm[$cid]);
    }

    public function getBytes(string $cid): string
    {
        if (!isset($this->perm[$cid])) {
            throw new BlobNotFoundException();
        }

        return $this->perm[$cid];
    }

    public function getStream(string $cid): StreamInterface
    {
        $bytes = $this->getBytes($cid);
        $resource = fopen('php://temp', 'r+');

        if ($resource === false) {
            throw new \RuntimeException('Could not open php://temp stream.');
        }

        fwrite($resource, $bytes);
        rewind($resource);

        return new Stream($resource);
    }

    public function delete(string $cid): void
    {
        if (!isset($this->perm[$cid])) {
            throw new BlobNotFoundException();
        }

        unset($this->perm[$cid], $this->quarantined[$cid]);
    }

    /**
     * @param string[] $cids
     */
    public function deleteMany(array $cids): void
    {
        foreach ($cids as $cid) {
            unset($this->perm[$cid], $this->quarantined[$cid]);
        }
    }

    public function deleteTemp(string $tempKey): void
    {
        if (!isset($this->temp[$tempKey])) {
            throw new BlobNotFoundException();
        }

        unset($this->temp[$tempKey]);
    }

    public function quarantine(string $cid): void
    {
        if (!isset($this->perm[$cid])) {
            throw new BlobNotFoundException();
        }

        $this->quarantined[$cid] = true;
    }

    public function unquarantine(string $cid): void
    {
        if (!isset($this->perm[$cid])) {
            throw new BlobNotFoundException();
        }

        unset($this->quarantined[$cid]);
    }

    public function isQuarantined(string $cid): bool
    {
        return isset($this->quarantined[$cid]);
    }
}
