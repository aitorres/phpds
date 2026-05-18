<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Repo;

use App\Domain\Repo\CidUtil;
use Tests\TestCase;

/**
 * Tests CID computation and base32-lower encode/decode roundtrip.
 */
class CidUtilTest extends TestCase
{
    public function testComputeForDagCborReturnsMultibasePrefixedString(): void
    {
        $cid = CidUtil::computeForDagCbor("\xf6"); // dag-cbor null

        // Must start with 'b' (multibase base32-lower)
        $this->assertStringStartsWith('b', $cid);
    }

    public function testComputedCidHasCorrectLength(): void
    {
        // Raw CID = 4-byte prefix + 32-byte sha256 = 36 bytes
        // Base32: ceil(36 * 8 / 5) = 58 chars, plus 'b' prefix = 59
        $cid = CidUtil::computeForDagCbor("\xf6");
        $this->assertSame(59, strlen($cid));
    }

    public function testDifferentInputsProduceDifferentCids(): void
    {
        $cid1 = CidUtil::computeForDagCbor("\xf6");            // null
        $cid2 = CidUtil::computeForDagCbor("\xf5");            // true
        $cid3 = CidUtil::computeForDagCbor("\x63abc");         // "abc"

        $this->assertNotSame($cid1, $cid2);
        $this->assertNotSame($cid1, $cid3);
        $this->assertNotSame($cid2, $cid3);
    }

    public function testSameInputProducesSameCid(): void
    {
        $bytes = "\x63abc";
        $this->assertSame(
            CidUtil::computeForDagCbor($bytes),
            CidUtil::computeForDagCbor($bytes)
        );
    }

    public function testRawBytesRoundtrip(): void
    {
        $cid  = CidUtil::computeForDagCbor("\xf6");
        $raw  = CidUtil::toRawBytes($cid);
        $back = CidUtil::fromRawBytes($raw);

        $this->assertSame($cid, $back);
    }

    public function testToRawBytesHasCorrectLength(): void
    {
        $cid = CidUtil::computeForDagCbor("\xf5");
        // 4-byte CID prefix + 32-byte SHA-256 = 36 bytes
        $this->assertSame(36, strlen(CidUtil::toRawBytes($cid)));
    }

    public function testToRawBytesStartsWithCidV1DagCborPrefix(): void
    {
        $cid = CidUtil::computeForDagCbor("\x60"); // dag-cbor ""
        $raw = CidUtil::toRawBytes($cid);

        // Byte 0 = version 1, byte 1 = dag-cbor codec 0x71
        $this->assertSame("\x01", $raw[0]);
        $this->assertSame("\x71", $raw[1]);
        // Byte 2 = sha2-256 code 0x12, byte 3 = 32-byte length 0x20
        $this->assertSame("\x12", $raw[2]);
        $this->assertSame("\x20", $raw[3]);
    }

    public function testToRawBytesThrowsForNonBase32Lower(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CidUtil::toRawBytes('Q' . str_repeat('a', 58)); // 'Q' = base58btc, not supported
    }
}
