<?php

declare(strict_types=1);

namespace App\Domain\Blob;

use Psr\Http\Message\StreamInterface;

/**
 * Binary blob content store.
 *
 * Mirrors the interface of `DiskBlobstore` from the reference TS PDS.
 */
interface BlobStore
{
    /**
     * Store raw bytes as a temporary upload.
     *
     * @return string  A temporary key identifying this upload.
     */
    public function putTemp(string $bytes): string;

    /**
     * Promote a temporary upload to permanent storage under the given CID.
     *
     * @throws BlobNotFoundException  If no temp blob exists for $tempKey.
     */
    public function makePermanent(string $tempKey, string $cid): void;

    /**
     * Store bytes directly under a permanent CID (e.g. during import/migration).
     */
    public function putPermanent(string $cid, string $bytes): void;

    public function hasTemp(string $tempKey): bool;

    public function hasStored(string $cid): bool;

    /**
     * @throws BlobNotFoundException
     */
    public function getBytes(string $cid): string;

    /**
     * @throws BlobNotFoundException
     */
    public function getStream(string $cid): StreamInterface;

    /**
     * @throws BlobNotFoundException
     */
    public function delete(string $cid): void;

    /**
     * @param string[] $cids
     */
    public function deleteMany(array $cids): void;

    /**
     * @throws BlobNotFoundException
     */
    public function deleteTemp(string $tempKey): void;

    /**
     * Mark a stored blob as quarantined (hidden from public access).
     *
     * @throws BlobNotFoundException
     */
    public function quarantine(string $cid): void;

    /**
     * Reverse a quarantine on a stored blob.
     *
     * @throws BlobNotFoundException
     */
    public function unquarantine(string $cid): void;

    public function isQuarantined(string $cid): bool;
}
