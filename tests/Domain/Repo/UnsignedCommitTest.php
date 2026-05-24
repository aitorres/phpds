<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\UnsignedCommit;
use Tests\TestCase;

class UnsignedCommitTest extends TestCase
{
    public function testGettersExposeConstructorValues(): void
    {
        $dataCid = CidUtil::computeForDagCbor('mst-root');
        $prevCid = CidUtil::computeForDagCbor('previous-commit');
        $commit  = new UnsignedCommit(
            did: 'did:plc:alice',
            data: new CidLink($dataCid),
            rev: '3m4example',
            prev: new CidLink($prevCid),
        );

        $this->assertSame('did:plc:alice', $commit->getDid());
        $this->assertSame($dataCid, $commit->getData()->getCid());
        $this->assertSame('3m4example', $commit->getRev());
        $this->assertSame($prevCid, $commit->getPrev()?->getCid());
    }

    public function testToMapIncludesVersionAndNullPrevByDefault(): void
    {
        $dataCid = CidUtil::computeForDagCbor('mst-root');
        $commit  = new UnsignedCommit(
            did: 'did:plc:alice',
            data: new CidLink($dataCid),
            rev: '3m4example',
        );

        $map = $commit->toMap();

        $this->assertSame('did:plc:alice', $map['did']);
        $this->assertSame(UnsignedCommit::VERSION, $map['version']);
        $this->assertInstanceOf(CidLink::class, $map['data']);
        $this->assertSame('3m4example', $map['rev']);
        $this->assertNull($map['prev']);
    }
}
