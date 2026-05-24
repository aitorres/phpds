<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\CborBytes;
use App\Domain\Repo\Commit;
use App\Domain\Repo\UnsignedCommit;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use Tests\TestCase;
use Tests\Support\RecordingKeypair;

class CommitTest extends TestCase
{
    public function testFromUnsignedSignsEncodedUnsignedCommitAndReturnsSignedCommit(): void
    {
        $encoder  = new NativeDagCborEncoder();
        $dataCid  = CidUtil::computeForDagCbor('mst-root');
        $prevCid  = CidUtil::computeForDagCbor('previous-commit');
        $unsigned = new UnsignedCommit(
            did: 'did:plc:alice',
            data: new CidLink($dataCid),
            rev: '3m4example',
            prev: new CidLink($prevCid),
        );
        $signature = str_repeat("\xab", 64);
        $signer    = new RecordingKeypair($signature);

        $commit = Commit::fromUnsigned($unsigned, $signer, $encoder);

        $this->assertSame($encoder->encode($unsigned->toMap()), $signer->getLastSignedMessage());
        $this->assertSame($unsigned->getDid(), $commit->getDid());
        $this->assertSame($unsigned->getData()->getCid(), $commit->getData()->getCid());
        $this->assertSame($unsigned->getRev(), $commit->getRev());
        $this->assertSame($unsigned->getPrev()?->getCid(), $commit->getPrev()?->getCid());
        $this->assertSame($signature, $commit->getSignature());
    }

    public function testToMapIncludesVersionAndWrapsSignatureInCborBytes(): void
    {
        $dataCid   = CidUtil::computeForDagCbor('mst-root');
        $signature = str_repeat("\xef", 64);
        $commit    = new Commit(
            did: 'did:plc:alice',
            data: new CidLink($dataCid),
            rev: '3m4example',
            prev: null,
            signature: $signature,
        );

        $map = $commit->toMap();

        $this->assertSame('did:plc:alice', $map['did']);
        $this->assertSame(Commit::VERSION, $map['version']);
        $this->assertInstanceOf(CidLink::class, $map['data']);
        $this->assertSame('3m4example', $map['rev']);
        $this->assertNull($map['prev']);
        $this->assertInstanceOf(CborBytes::class, $map['sig']);
        $this->assertSame($signature, $map['sig']->getBytes());
    }
}
