<?php

declare(strict_types=1);

namespace App\Domain\Common;

use App\Domain\Common\Base32;

/**
 * Atproto TID (Timestamp Identifier).
 *
 * A TID is a 13-character base32-sortable string encoding 64 bits:
 *   bit 63       — 0 (reserved, must be zero)
 *   bits 53..62  — 53-bit microsecond unix timestamp
 *   bits 0..9    — 10-bit random clock identifier
 */
final class Tid
{
    public const ALPHABET = '234567abcdefghijklmnopqrstuvwxyz';

    public const LENGTH = 13;

    private static int $lastTimestamp = 0;
    private static int $clockId = -1;

    private function __construct()
    {
    }

    /**
     * Generate a fresh, monotonically-increasing TID for the current process.
     */
    public static function next(): string
    {
        if (self::$clockId === -1) {
            self::$clockId = random_int(0, 1023);
        }

        $now = self::microseconds();
        if ($now <= self::$lastTimestamp) {
            $now = self::$lastTimestamp + 1;
        }
        self::$lastTimestamp = $now;

        return self::encode($now, self::$clockId);
    }

    /**
     * Encode a (timestamp, clockId) pair into a 13-character TID string.
     */
    public static function encode(int $timestampMicros, int $clockId): string
    {
        $value = (($timestampMicros & ((1 << 53) - 1)) << 10) | ($clockId & 0x3ff);
        return Base32::encodeInt($value, self::LENGTH, self::ALPHABET);
    }

    public static function isValid(string $tid): bool
    {
        if (strlen($tid) !== self::LENGTH) {
            return false;
        }
        for ($i = 0; $i < self::LENGTH; $i++) {
            if (strpos(self::ALPHABET, $tid[$i]) === false) {
                return false;
            }
        }
        return true;
    }

    private static function microseconds(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }
}
