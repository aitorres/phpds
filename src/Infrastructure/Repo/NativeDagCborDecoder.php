<?php

declare(strict_types=1);

namespace App\Infrastructure\Repo;

use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\DagCborDecoder;
use RuntimeException;

/**
 * Pure-PHP dag-cbor decoder.
 *
 * Parses a single CBOR item from the start of $bytes using a pass-by-reference
 * cursor. Unknown CBOR tags (other than tag 42) and unsupported additional info
 * values throw RuntimeException.
 */
class NativeDagCborDecoder implements DagCborDecoder
{
    public function decode(string $bytes): mixed
    {
        $cursor = 0;
        $value  = $this->decodeValue($bytes, $cursor);

        if ($cursor !== strlen($bytes)) {
            throw new RuntimeException('dag-cbor: too many terminals');
        }

        return $value;
    }

    /**
     * Decode all concatenated dag-cbor values from $bytes.
     *
     * @return list<mixed>
     */
    public function decodeAll(string $bytes): array
    {
        $cursor  = 0;
        $results = [];
        $total   = strlen($bytes);

        while ($cursor < $total) {
            $results[] = $this->decodeValue($bytes, $cursor);
        }

        return $results;
    }

    private function decodeValue(string $bytes, int &$cursor): mixed
    {
        if ($cursor >= strlen($bytes)) {
            throw new RuntimeException('Unexpected end of dag-cbor input');
        }

        $b          = ord($bytes[$cursor++]);
        $major      = $b >> 5;
        $additional = $b & 0x1f;

        if ($major === 7) {
            return $this->decodeSpecial($bytes, $cursor, $additional);
        }

        $length = $this->readLength($bytes, $cursor, $additional);

        if ($major === 6) {
            return $this->decodeTag($bytes, $cursor, $length);
        }

        return match ($major) {
            0       => $length,
            1       => -1 - $length,
            2       => new CborBytes($this->readRaw($bytes, $cursor, $length)),
            3       => $this->readRaw($bytes, $cursor, $length),
            4       => $this->decodeArray($bytes, $cursor, $length),
            default => $this->decodeMap($bytes, $cursor, $length),   // major === 5
        };
    }

    /**
     * Decode a CBOR argument (length / integer value) from additional-info bits.
     */
    private function readLength(string $bytes, int &$cursor, int $additional): int
    {
        if ($additional <= 23) {
            return $additional;
        }

        if ($additional === 24) {
            return ord($bytes[$cursor++]);
        }

        if ($additional === 25) {
            $v = $this->unpackUint16(substr($bytes, $cursor, 2));
            $cursor += 2;
            return $v;
        }

        if ($additional === 26) {
            $v = $this->unpackUint32(substr($bytes, $cursor, 4));
            $cursor += 4;
            return $v;
        }

        if ($additional === 27) {
            $v = $this->unpackUint64(substr($bytes, $cursor, 8));
            $cursor += 8;
            return $v;
        }

        throw new RuntimeException("Unsupported additional info for length: {$additional}");
    }

    /**
     * Read $length raw bytes and advance the cursor.
     */
    private function readRaw(string $bytes, int &$cursor, int $length): string
    {
        $chunk   = substr($bytes, $cursor, $length);
        $cursor += $length;
        return $chunk;
    }

    /**
     * Decode major-type 7 (float / simple value).
     */
    private function decodeSpecial(string $bytes, int &$cursor, int $additional): mixed
    {
        return match ($additional) {
            20      => false,
            21      => true,
            22      => null,
            23      => null,  // CBOR "undefined" — coerce to null per dag-cbor sloppy-decode
            25      => $this->decodeHalfFloat($bytes, $cursor),
            26      => $this->decodeSingleFloat($bytes, $cursor),
            27      => $this->decodeDouble($bytes, $cursor),
            default => throw new RuntimeException("Unsupported CBOR simple/float additional: {$additional}"),
        };
    }

    /**
     * @return list<mixed>
     */
    private function decodeArray(string $bytes, int &$cursor, int $count): array
    {
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $result[] = $this->decodeValue($bytes, $cursor);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMap(string $bytes, int &$cursor, int $count): array
    {
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $key = $this->decodeValue($bytes, $cursor);

            if (!is_string($key)) {
                throw new RuntimeException('dag-cbor map keys must be UTF-8 text strings');
            }

            if (array_key_exists($key, $result)) {
                throw new RuntimeException("CBOR decode error: found repeat map key \"{$key}\"");
            }

            $result[$key] = $this->decodeValue($bytes, $cursor);
        }

        return $result;
    }

    private function decodeTag(string $bytes, int &$cursor, int $tag): CidLink
    {
        if ($tag !== 42) {
            throw new RuntimeException("Unsupported CBOR tag: {$tag}");
        }

        $inner = $this->decodeValue($bytes, $cursor);

        if (!$inner instanceof CborBytes) {
            throw new RuntimeException('CBOR tag 42 value must be a byte string');
        }

        $cidBytes = $inner->getBytes();

        if ($cidBytes === '' || $cidBytes[0] !== "\x00") {
            throw new RuntimeException('Invalid CID for CBOR tag 42; expected leading 0x00 identity multibase prefix');
        }

        return new CidLink(CidUtil::fromRawBytes(substr($cidBytes, 1)));
    }

    private function unpackUint16(string $bytes): int
    {
        $r = unpack('n', $bytes);

        if (!is_array($r) || !isset($r[1]) || !is_int($r[1])) {
            throw new RuntimeException('unpack(n) failed');
        }

        return $r[1];
    }

    private function unpackUint32(string $bytes): int
    {
        $r = unpack('N', $bytes);

        if (!is_array($r) || !isset($r[1]) || !is_int($r[1])) {
            throw new RuntimeException('unpack(N) failed');
        }

        return $r[1];
    }

    private function unpackUint64(string $bytes): int
    {
        $r = unpack('J', $bytes);

        if (!is_array($r) || !isset($r[1]) || !is_int($r[1])) {
            throw new RuntimeException('unpack(J) failed');
        }

        return $r[1];
    }

    private function unpackDouble(string $bytes): float
    {
        $r = unpack('E', $bytes);

        if (!is_array($r) || !isset($r[1]) || !is_float($r[1])) {
            throw new RuntimeException('unpack(E) failed');
        }

        return $r[1];
    }

    private function decodeDouble(string $bytes, int &$cursor): float
    {
        $val = $this->unpackDouble($this->readRaw($bytes, $cursor, 8));

        if (is_nan($val)) {
            throw new RuntimeException('dag-cbor: NaN is not supported');
        }

        if (is_infinite($val)) {
            $label = $val > 0 ? 'Infinity' : '-Infinity';
            throw new RuntimeException("dag-cbor: {$label} is not supported");
        }

        return $val;
    }

    /**
     * Decode a 16-bit IEEE 754 half-precision float, throw on NaN / Infinity.
     */
    private function decodeHalfFloat(string $bytes, int &$cursor): float
    {
        $r = unpack('n', substr($bytes, $cursor, 2));

        if (!is_array($r) || !isset($r[1]) || !is_int($r[1])) {
            throw new RuntimeException('unpack(n) failed for half-float');
        }

        $cursor += 2;
        $uint16   = $r[1];
        $exponent = ($uint16 >> 10) & 0x1f;
        $mantissa = $uint16 & 0x3ff;
        $sign     = ($uint16 & 0x8000) ? -1.0 : 1.0;

        if ($exponent === 0x1f) {
            if ($mantissa !== 0) {
                throw new RuntimeException('dag-cbor: NaN is not supported');
            }

            $label = $sign > 0 ? 'Infinity' : '-Infinity';
            throw new RuntimeException("dag-cbor: {$label} is not supported");
        }

        if ($exponent === 0) {
            return $sign * ($mantissa / 1024.0) * (2.0 ** (-14));
        }

        return $sign * (1.0 + $mantissa / 1024.0) * (2.0 ** ($exponent - 15));
    }

    /**
     * Decode a 32-bit IEEE 754 single-precision float, throw on NaN / Infinity.
     */
    private function decodeSingleFloat(string $bytes, int &$cursor): float
    {
        $r = unpack('G', substr($bytes, $cursor, 4));

        if (!is_array($r) || !isset($r[1]) || !is_float($r[1])) {
            throw new RuntimeException('unpack(G) failed for single-float');
        }

        $cursor += 4;
        $val     = $r[1];

        if (is_nan($val)) {
            throw new RuntimeException('dag-cbor: NaN is not supported');
        }

        if (is_infinite($val)) {
            $label = $val > 0 ? 'Infinity' : '-Infinity';
            throw new RuntimeException("dag-cbor: {$label} is not supported");
        }

        return $val;
    }
}
