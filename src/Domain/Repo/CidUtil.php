<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use App\Domain\Common\Base32;
use InvalidArgumentException;

/**
 * CID utility: compute CIDv1 (dag-cbor / sha2-256) and encode/decode between
 * the multibase base32-lower string form and raw bytes.
 *
 * Raw CID layout (36 bytes for sha2-256):
 *   0x01        — version 1 (varint)
 *   0x71        — dag-cbor codec (varint)
 *   0x12        — sha2-256 hash-function code (multihash varint)
 *   0x20        — 32-byte digest length (multihash varint)
 *   <32 bytes>  — SHA-256 digest
 *
 * Multibase prefix 'b' = base32 lowercase, no padding (RFC 4648).
 */
final class CidUtil
{
    /** CIDv1 / dag-cbor / sha2-256 prefix (4 bytes). */
    private const CID_PREFIX = "\x01\x71\x12\x20";

    /**
     * Compute a CIDv1 (dag-cbor + sha2-256) for the given dag-cbor bytes.
     */
    public static function computeForDagCbor(string $cborBytes): string
    {
        $hash   = hash('sha256', $cborBytes, true);
        $raw    = self::CID_PREFIX . $hash;

        return 'b' . Base32::encode($raw);
    }

    /**
     * Parse and validate a base32-lower CIDv1 string, returning the canonical string.
     *
     * @throws InvalidArgumentException for invalid CID strings
     */
    public static function parseCid(string $cid): string
    {
        $rawBytes = self::toRawBytes($cid);

        if (strlen($rawBytes) < 4 || $rawBytes[0] !== "\x01") {
            throw new InvalidArgumentException("Invalid CIDv1 string: '{$cid}'");
        }

        return $cid;
    }

    /**
     * Parse a CID string, returning null instead of throwing for invalid input.
     */
    public static function parseCidSafe(string $cid): ?string
    {
        try {
            return self::parseCid($cid);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Validate a CID string, throwing with message "Invalid CID string" if invalid.
     *
     * @throws InvalidArgumentException
     */
    public static function ensureValidCidString(string $cid): void
    {
        try {
            self::parseCid($cid);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("Invalid CID string: '{$cid}'");
        }
    }

    /**
     * Convert a multibase base32-lower CID string to its raw bytes.
     *
     * @throws InvalidArgumentException for non-'b' multibase prefixes
     */
    public static function toRawBytes(string $cid): string
    {
        if ($cid === '' || $cid[0] !== 'b') {
            throw new InvalidArgumentException(
                "Only base32-lower CIDs (multibase prefix 'b') are supported; got '{$cid[0]}'"
            );
        }

        return Base32::decode(substr($cid, 1));
    }

    /**
     * Convert raw CID bytes to a multibase base32-lower string.
     */
    public static function fromRawBytes(string $rawBytes): string
    {
        return 'b' . Base32::encode($rawBytes);
    }
}
