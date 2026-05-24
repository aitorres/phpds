<?php

declare(strict_types=1);

namespace App\Domain\Common;

use InvalidArgumentException;

/**
 * Base32 (RFC 4648) lowercase, no-padding codec.
 *
 * Shared helper used wherever the project needs to encode/decode bytes using
 * the base32 lowercase alphabet (e.g. CIDv1 multibase strings and invite
 * code generation).
 */
final class Base32
{
    public const ALPHABET = 'abcdefghijklmnopqrstuvwxyz234567';

    /**
     * Encode a non-negative integer as a fixed-length base32 string using the
     * given 32-character alphabet.  Extracts $length groups of 5 bits, MSB
     * first (big-endian).
     */
    public static function encodeInt(int $value, int $length, string $alphabet = self::ALPHABET): string
    {
        $out = '';
        for ($i = $length - 1; $i >= 0; $i--) {
            $out .= $alphabet[($value >> ($i * 5)) & 0x1f];
        }
        return $out;
    }

    /**
     * Encode raw bytes as base32 lowercase, without padding.
     */
    public static function encode(string $bytes): string
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
     * Decode a base32 lowercase string (no padding) into bytes.
     *
     * @throws InvalidArgumentException for invalid characters
     */
    public static function decode(string $encoded): string
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
