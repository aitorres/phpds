<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CidUtil;
use App\Domain\Repo\EmptyMst;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use Tests\TestCase;

class EmptyMstTest extends TestCase
{
    public function testToMapReturnsExpectedEmptyMstShape(): void
    {
        $this->assertSame(
            [
                'l' => null,
                'e' => [],
            ],
            EmptyMst::toMap()
        );
    }

    public function testEncodeReturnsEncodedBytesAndMatchingCid(): void
    {
        $encoder = new NativeDagCborEncoder();

        [$bytes, $cid] = EmptyMst::encode($encoder);

        $this->assertSame($encoder->encode(EmptyMst::toMap()), $bytes);
        $this->assertSame(CidUtil::computeForDagCbor($bytes), $cid);
    }
}
