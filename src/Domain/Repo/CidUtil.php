<?php

declare(strict_types=1);

namespace App\Domain\Repo;

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
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

    /** CIDv1 / dag-cbor / sha2-256 prefix (4 bytes). */
    private const CID_PREFIX = "\x01\x71\x12\x20";

    /**
     * Compute a CIDv1 (dag-cbor + sha2-256) for the given dag-cbor bytes.
     */
    public static function computeForDagCbor(string $cborBytes): string
    {
        $hash   = hash('sha256', $cborBytes, true);
        $raw    = self::CID_PREFIX . $hash;

        return 'b' . self::base32Lower($raw);
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

        return self::base32LowerDecode(substr($cid, 1));
    }

    /**
     * Convert raw CID bytes to a multibase base32-lower string.
     */
    public static function fromRawBytes(string $rawBytes): string
    {
        return 'b' . self::base32Lower($rawBytes);
    }

    /**
     * Calculates the base32 lowercase encoding of the given bytes, without padding.
     */
    private static function base32Lower(string $bytes): string
    {
        $result = '';
        $bits   = 0;
        $value  = 0;
        $len    = strlen($bytes);

        for ($i = 0; $i < $len; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits  -= 5;
                $result .= self::ALPHABET[($value >> $bits) & 0x1f];
            }
        }

        if ($bits > 0) {
            $result .= self::ALPHABET[($value << (5 - $bits)) & 0x1f];
        }

        return $result;
    }

    /**
     * Decodes a base32 lowercase string into bytes.
     *
     * @throws InvalidArgumentException for invalid characters
     */
    private static function base32LowerDecode(string $encoded): string
    {
        /** @var array<string, int>|null $table */
        static $table = null;

        if ($table === null) {
            $table = [];
            for ($i = 0; $i < 32; $i++) {
                $table[self::ALPHABET[$i]] = $i;
            }
        }

        $result = '';
        $bits   = 0;
        $value  = 0;
        $len    = strlen($encoded);

        for ($i = 0; $i < $len; $i++) {
            $c = $encoded[$i];

            if (!isset($table[$c])) {
                throw new InvalidArgumentException("Invalid base32 character: '{$c}'");
            }

            $value = ($value << 5) | $table[$c];
            $bits += 5;

            if ($bits >= 8) {
                $bits  -= 8;
                $result .= chr(($value >> $bits) & 0xff);
            }
        }

        return $result;
    }
}
