<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Repo;

use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Infrastructure\Repo\NativeCarReader;
use App\Infrastructure\Repo\NativeCarWriter;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use Tests\TestCase;

/**
 * Tests CARv1 writer and reader together via a roundtrip.
 */
class CarRoundtripTest extends TestCase
{
    private NativeDagCborEncoder $encoder;
    private NativeCarWriter $writer;
    private NativeCarReader $reader;

    protected function setUp(): void
    {
        $this->encoder = new NativeDagCborEncoder();
        $decoder       = new NativeDagCborDecoder();
        $this->writer  = new NativeCarWriter($this->encoder);
        $this->reader  = new NativeCarReader($decoder);
    }

    public function testSingleBlockRoundtrip(): void
    {
        $blockData = $this->encoder->encode(['$type' => 'app.bsky.feed.post', 'text' => 'hello']);
        $cid       = CidUtil::computeForDagCbor($blockData);

        $car     = $this->writer->write([$cid], [$cid => $blockData]);
        $result  = $this->reader->read($car);

        $this->assertSame([$cid], $result['roots']);
        $this->assertArrayHasKey($cid, $result['blocks']);
        $this->assertSame($blockData, $result['blocks'][$cid]);
    }

    public function testMultiBlockRoundtrip(): void
    {
        $leaf1Data = $this->encoder->encode(['text' => 'leaf1']);
        $leaf2Data = $this->encoder->encode(['text' => 'leaf2']);
        $cid1      = CidUtil::computeForDagCbor($leaf1Data);
        $cid2      = CidUtil::computeForDagCbor($leaf2Data);

        $rootData  = $this->encoder->encode(['children' => [new CidLink($cid1), new CidLink($cid2)]]);
        $rootCid   = CidUtil::computeForDagCbor($rootData);

        $blocks = [
            $rootCid => $rootData,
            $cid1    => $leaf1Data,
            $cid2    => $leaf2Data,
        ];

        $car    = $this->writer->write([$rootCid], $blocks);
        $result = $this->reader->read($car);

        $this->assertSame([$rootCid], $result['roots']);
        $this->assertCount(3, $result['blocks']);

        foreach ($blocks as $cid => $data) {
            $this->assertArrayHasKey($cid, $result['blocks']);
            $this->assertSame($data, $result['blocks'][$cid], "Block mismatch for CID {$cid}");
        }
    }

    public function testEmptyBlockListRoundtrip(): void
    {
        $leafData = $this->encoder->encode(['root' => true]);
        $rootCid  = CidUtil::computeForDagCbor($leafData);

        // A CAR can technically list roots without embedding their blocks.
        $car    = $this->writer->write([$rootCid], []);
        $result = $this->reader->read($car);

        $this->assertSame([$rootCid], $result['roots']);
        $this->assertEmpty($result['blocks']);
    }

    public function testCarBytesStartWithValidVarint(): void
    {
        $data = $this->encoder->encode(['a' => 1]);
        $cid  = CidUtil::computeForDagCbor($data);
        $car  = $this->writer->write([$cid], [$cid => $data]);

        // First byte must be a uvarint for the header length (< 0x80 for short headers)
        $firstByte = ord($car[0]);
        $this->assertGreaterThan(0, $firstByte);
    }

    public function testContentAddressabilityIsPreserved(): void
    {
        $data    = $this->encoder->encode(['text' => 'integrity check']);
        $cid     = CidUtil::computeForDagCbor($data);
        $car     = $this->writer->write([$cid], [$cid => $data]);
        $result  = $this->reader->read($car);

        // The CID of the recovered block must match what we computed
        $recoveredData = $result['blocks'][$cid];
        $recoveredCid  = CidUtil::computeForDagCbor($recoveredData);

        $this->assertSame($cid, $recoveredCid);
    }
}
