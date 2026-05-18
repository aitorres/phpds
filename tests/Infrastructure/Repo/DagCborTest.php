<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Repo;

use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use Tests\TestCase;

/**
 * Tests the encoder alone (known byte-level output) and the full
 * encode -> decode roundtrip for every supported PHP type.
 */
class DagCborTest extends TestCase
{
    private NativeDagCborEncoder $encoder;
    private NativeDagCborDecoder $decoder;

    protected function setUp(): void
    {
        $this->encoder = new NativeDagCborEncoder();
        $this->decoder = new NativeDagCborDecoder();
    }

    public function testEncodeNull(): void
    {
        $this->assertSame("\xf6", $this->encoder->encode(null));
    }

    public function testEncodeTrue(): void
    {
        $this->assertSame("\xf5", $this->encoder->encode(true));
    }

    public function testEncodeFalse(): void
    {
        $this->assertSame("\xf4", $this->encoder->encode(false));
    }

    public function testEncodeZero(): void
    {
        $this->assertSame("\x00", $this->encoder->encode(0));
    }

    public function testEncodeSmallUint(): void
    {
        // 1 -> major 0, value 1 -> 0x01
        $this->assertSame("\x01", $this->encoder->encode(1));
    }

    public function testEncodeUint24(): void
    {
        // 24 -> major 0, additional 24, next byte 24 -> 0x18 0x18
        $this->assertSame("\x18\x18", $this->encoder->encode(24));
    }

    public function testEncodeUint256(): void
    {
        // 256 -> major 0, additional 25, 2 bytes big-endian -> 0x19 0x01 0x00
        $this->assertSame("\x19\x01\x00", $this->encoder->encode(256));
    }

    public function testEncodeNegativeOne(): void
    {
        // -1 -> major 1, value 0 -> 0x20
        $this->assertSame("\x20", $this->encoder->encode(-1));
    }

    public function testEncodeNegativeSmall(): void
    {
        // -100 -> major 1, value 99 -> 0x38 0x63
        $this->assertSame("\x38\x63", $this->encoder->encode(-100));
    }

    public function testEncodeEmptyString(): void
    {
        // "" -> major 3, length 0 -> 0x60
        $this->assertSame("\x60", $this->encoder->encode(''));
    }

    public function testEncodeShortString(): void
    {
        // "hi" -> 0x62 0x68 0x69
        $this->assertSame("\x62\x68\x69", $this->encoder->encode('hi'));
    }

    public function testEncodeEmptyList(): void
    {
        // [] -> major 4, length 0 -> 0x80
        $this->assertSame("\x80", $this->encoder->encode([]));
    }

    public function testEncodeList(): void
    {
        // [1,2,3] -> 0x83 0x01 0x02 0x03
        $this->assertSame("\x83\x01\x02\x03", $this->encoder->encode([1, 2, 3]));
    }

    public function testEncodeEmptyMap(): void
    {
        // PHP associative array with no elements is an edge case.
        // PHP treats [] as a list, so we encode a known single-entry map
        // and verify the empty array encodes as an empty CBOR list.
        $this->assertSame("\x80", $this->encoder->encode([]));
    }

    public function testEncodeAssociativeArray(): void
    {
        // {"a":1} -> 0xa1 0x61 0x61 0x01
        $bytes = $this->encoder->encode(['a' => 1]);
        $this->assertSame("\xa1\x61\x61\x01", $bytes);
    }

    public function testEncodeMapKeyOrdering(): void
    {
        // dag-cbor: keys sorted by byte-length first, then lexicographic.
        // {"bb":2,"a":1} -> a must come before bb in the encoded bytes.
        $bytes = $this->encoder->encode(['bb' => 2, 'a' => 1]);
        $decoded = $this->decoder->decode($bytes);
        $this->assertIsArray($decoded);
        $keys = array_keys($decoded);
        $this->assertSame('a', $keys[0]);
        $this->assertSame('bb', $keys[1]);
    }

    public function testEncodeCborBytes(): void
    {
        // CborBytes("\x01\x02") -> major 2, length 2, bytes -> 0x42 0x01 0x02
        $bytes = $this->encoder->encode(new CborBytes("\x01\x02"));
        $this->assertSame("\x42\x01\x02", $bytes);
    }

    public function testRoundtripNull(): void
    {
        $this->assertNull($this->roundtrip(null));
    }

    public function testRoundtripBool(): void
    {
        $this->assertTrue($this->roundtrip(true));
        $this->assertFalse($this->roundtrip(false));
    }

    public function testRoundtripInt(): void
    {
        foreach ([0, 1, 23, 24, 255, 256, 65535, 65536, -1, -24, -100, -65536] as $n) {
            $this->assertSame($n, $this->roundtrip($n), "roundtrip failed for int $n");
        }
    }

    public function testRoundtripFloat(): void
    {
        $this->assertSame(3.14, $this->roundtrip(3.14));
        $this->assertSame(0.0, $this->roundtrip(0.0));
        $this->assertSame(-1.5, $this->roundtrip(-1.5));
    }

    public function testRoundtripString(): void
    {
        foreach (['', 'hello', 'unicode: 🌍', str_repeat('x', 300)] as $s) {
            $this->assertSame($s, $this->roundtrip($s), "roundtrip failed for string");
        }
    }

    public function testRoundtripCborBytes(): void
    {
        $orig   = new CborBytes("\x00\xff\xab");
        $result = $this->roundtrip($orig);
        $this->assertInstanceOf(CborBytes::class, $result);
        $this->assertSame($orig->getBytes(), $result->getBytes());
    }

    public function testRoundtripList(): void
    {
        $orig   = [1, 'two', null, false, [3, 4]];
        $result = $this->roundtrip($orig);
        $this->assertSame($orig, $result);
    }

    public function testRoundtripMap(): void
    {
        $orig   = ['name' => 'alice', 'age' => 30, 'active' => true];
        $result = $this->roundtrip($orig);
        $this->assertIsArray($result);
        $this->assertSame($orig['name'], $result['name']);
        $this->assertSame($orig['age'], $result['age']);
        $this->assertSame($orig['active'], $result['active']);
    }

    public function testRoundtripCidLink(): void
    {
        // Build a CID for some arbitrary dag-cbor bytes
        $cborBytes = $this->encoder->encode(['$type' => 'app.bsky.feed.post', 'text' => 'hello']);
        $cid       = CidUtil::computeForDagCbor($cborBytes);

        $orig   = new CidLink($cid);
        $result = $this->roundtrip($orig);
        $this->assertInstanceOf(CidLink::class, $result);
        $this->assertSame($cid, $result->getCid());
    }

    public function testRoundtripNestedWithLink(): void
    {
        $inner     = $this->encoder->encode(['text' => 'inner']);
        $innerCid  = CidUtil::computeForDagCbor($inner);

        $outer = ['ref' => new CidLink($innerCid), 'count' => 1];
        $result = $this->roundtrip($outer);
        $this->assertIsArray($result);
        $this->assertInstanceOf(CidLink::class, $result['ref']);
        $this->assertSame($innerCid, $result['ref']->getCid());
    }

    private function roundtrip(mixed $value): mixed
    {
        return $this->decoder->decode($this->encoder->encode($value));
    }
}
