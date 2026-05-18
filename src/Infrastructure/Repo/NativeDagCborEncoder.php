<?php

declare(strict_types=1);

namespace App\Infrastructure\Repo;

use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\DagCborEncoder;
use InvalidArgumentException;

/**
 * Pure-PHP dag-cbor encoder.
 *
 * Implements the dag-cbor profile of CBOR (RFC 7049 / RFC 8949):
 *   - All allowed CBOR types (see DagCborEncoder interface).
 *   - Maps are serialised in "length-first, then lexicographic" key order
 *     (deterministic encoding per dag-cbor spec).
 *   - Floats are always 64-bit IEEE 754 (major type 7, additional info 27).
 *   - Only tag 42 (CID link) is emitted.
 */
class NativeDagCborEncoder implements DagCborEncoder
{
    public function encode(mixed $value): string
    {
        return $this->encodeValue($value);
    }

    private function encodeValue(mixed $value): string
    {
        if ($value === null) {
            return "\xf6";
        }

        if ($value === true) {
            return "\xf5";
        }

        if ($value === false) {
            return "\xf4";
        }

        if (is_int($value)) {
            return $this->encodeInt($value);
        }

        if (is_float($value)) {
            if (is_nan($value)) {
                throw new InvalidArgumentException('dag-cbor: NaN is not supported');
            }

            if (is_infinite($value)) {
                $label = $value > 0 ? 'Infinity' : '-Infinity';
                throw new InvalidArgumentException("dag-cbor: {$label} is not supported");
            }

            return "\xfb" . $this->packBigEndianDouble($value);
        }

        if (is_string($value)) {
            return $this->encodeHead(3, strlen($value)) . $value;
        }

        if ($value instanceof CborBytes) {
            $b = $value->getBytes();
            return $this->encodeHead(2, strlen($b)) . $b;
        }

        if ($value instanceof CidLink) {
            return $this->encodeCidLink($value);
        }

        if (is_array($value)) {
            return $this->encodeArray($value);
        }

        throw new InvalidArgumentException('Unsupported type for dag-cbor encoding: ' . gettype($value));
    }

    private function encodeInt(int $n): string
    {
        if ($n >= 0) {
            return $this->encodeHead(0, $n);
        }

        return $this->encodeHead(1, -$n - 1);
    }

    /**
     * Encode a CID link as CBOR tag(42) + bstr("\x00" + raw_cid_bytes).
     */
    private function encodeCidLink(CidLink $link): string
    {
        $rawCid   = CidUtil::toRawBytes($link->getCid());
        $cidBytes = "\x00" . $rawCid;

        // Tag 42: major type 6, additional 24, value 42 -> 0xd8 0x2a
        return "\xd8\x2a" . $this->encodeHead(2, strlen($cidBytes)) . $cidBytes;
    }

    private function encodeArray(mixed $arr): string
    {
        /** @var array<mixed> $arr */
        if ($this->isList($arr)) {
            $out = $this->encodeHead(4, count($arr));

            foreach ($arr as $item) {
                $out .= $this->encodeValue($item);
            }

            return $out;
        }

        // Map: sort keys by byte-length first, then lexicographic (dag-cbor spec).
        $keys = array_keys($arr);

        usort($keys, static function (mixed $a, mixed $b): int {
            $sa = (string) $a;
            $sb = (string) $b;
            $la = strlen($sa);
            $lb = strlen($sb);

            return $la !== $lb ? $la - $lb : strcmp($sa, $sb);
        });

        $out = $this->encodeHead(5, count($arr));

        foreach ($keys as $k) {
            $ks  = (string) $k;
            $out .= $this->encodeHead(3, strlen($ks)) . $ks;
            $out .= $this->encodeValue($arr[$k]);
        }

        return $out;
    }

    /**
     * Returns true if $arr has sequential integer keys starting from 0.
     *
     * @param array<mixed> $arr
     */
    private function isList(array $arr): bool
    {
        if (empty($arr)) {
            return true;
        }

        $i = 0;

        foreach ($arr as $k => $_) {
            if ($k !== $i++) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encode a CBOR head byte (major type + argument).
     *
     * @param int $major 0–7
     * @param int $value non-negative argument value
     */
    private function encodeHead(int $major, int $value): string
    {
        $m = ($major & 0x7) << 5;

        if ($value <= 23) {
            return chr(($m | $value) & 0xff);
        }

        if ($value <= 0xff) {
            return chr(($m | 24) & 0xff) . chr($value & 0xff);
        }

        if ($value <= 0xffff) {
            return chr(($m | 25) & 0xff) . pack('n', $value);
        }

        if ($value <= 0xffffffff) {
            return chr(($m | 26) & 0xff) . pack('N', $value);
        }

        return chr(($m | 27) & 0xff) . pack('J', $value);
    }

    private function packBigEndianDouble(float $v): string
    {
        return pack('E', $v);
    }
}
