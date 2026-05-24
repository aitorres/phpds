<?php

declare(strict_types=1);

namespace App\Domain\Common;

use InvalidArgumentException;

/**
 * Base58 (btc alphabet) codec.
 *
 * Used by the `did:key:z...` multibase encoding (the leading `z` is the
 * multibase prefix for base58btc).
 */
final class Base58Btc
{
    public const ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $leadingZeros = 0;
        $len = strlen($bytes);
        while ($leadingZeros < $len && $bytes[$leadingZeros] === "\x00") {
            $leadingZeros++;
        }

        $digits = [];
        for ($i = $leadingZeros; $i < $len; $i++) {
            $carry = ord($bytes[$i]);
            foreach ($digits as $idx => $digit) {
                $carry += $digit << 8;
                $digits[$idx] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
            while ($carry > 0) {
                $digits[] = $carry % 58;
                $carry = intdiv($carry, 58);
            }
        }

        $out = str_repeat('1', $leadingZeros);
        for ($i = count($digits) - 1; $i >= 0; $i--) {
            $out .= self::ALPHABET[$digits[$i]];
        }

        return $out;
    }

    public static function decode(string $encoded): string
    {
        if ($encoded === '') {
            return '';
        }

        /** @var array<string, int>|null $table */
        static $table = null;
        if ($table === null) {
            $table = [];
            for ($i = 0; $i < 58; $i++) {
                $table[self::ALPHABET[$i]] = $i;
            }
        }

        $leadingOnes = 0;
        $len = strlen($encoded);
        while ($leadingOnes < $len && $encoded[$leadingOnes] === '1') {
            $leadingOnes++;
        }

        $bytes = [];
        for ($i = $leadingOnes; $i < $len; $i++) {
            $c = $encoded[$i];
            if (!isset($table[$c])) {
                throw new InvalidArgumentException("Invalid base58 character: '{$c}'");
            }
            $carry = $table[$c];
            foreach ($bytes as $idx => $byte) {
                $carry += $byte * 58;
                $bytes[$idx] = $carry & 0xff;
                $carry >>= 8;
            }
            while ($carry > 0) {
                $bytes[] = $carry & 0xff;
                $carry >>= 8;
            }
        }

        $out = str_repeat("\x00", $leadingOnes);
        for ($i = count($bytes) - 1; $i >= 0; $i--) {
            $out .= chr($bytes[$i]);
        }

        return $out;
    }
}
