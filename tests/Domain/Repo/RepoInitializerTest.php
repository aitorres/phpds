<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\ActorStore\ActorStore;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\Commit;
use App\Domain\Repo\EmptyMst;
use App\Domain\Repo\RepoBlock;
use App\Domain\Repo\RepoBlockRepository;
use App\Domain\Repo\RepoInitializer;
use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootRepository;
use App\Domain\Repo\UnsignedCommit;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use Prophecy\Argument;
use Tests\TestCase;
use Tests\Support\RecordingKeypair;

class RepoInitializerTest extends TestCase
{
    public function testInitializeSignsUnsignedCommitAndPersistsCommitBlock(): void
    {
        $did       = 'did:plc:alice';
        $encoder   = new NativeDagCborEncoder();
        $signature = str_repeat("\xcd", 64);
        $signer    = new RecordingKeypair($signature);

        [$mstBytes, $mstCid] = EmptyMst::encode($encoder);

        $repoBlocks = $this->prophesize(RepoBlockRepository::class);
        $repoRoot   = $this->prophesize(RepoRootRepository::class);
        $store      = $this->prophesize(ActorStore::class);

        $repoBlocks->save(Argument::that(function (RepoBlock $block) use ($mstBytes, $mstCid): bool {
            return $block->getCid() === $mstCid
                && $block->getRepoRev() !== ''
                && $block->getSize() === strlen($mstBytes)
                && $block->getContent() === $mstBytes;
        }))->shouldBeCalled();

        $repoBlocks->save(Argument::that(function (RepoBlock $block) use ($did, $encoder, $mstCid, $signature): bool {
            $unsigned = new UnsignedCommit(
                did: $did,
                data: new CidLink($mstCid),
                rev: $block->getRepoRev(),
                prev: null,
            );
            $commit       = new Commit($did, new CidLink($mstCid), $block->getRepoRev(), null, $signature);
            $commitBytes  = $encoder->encode($commit->toMap());
            $expectedCid  = CidUtil::computeForDagCbor($commitBytes);

            return $block->getCid() === $expectedCid
                && $block->getSize() === strlen($commitBytes)
                && $block->getContent() === $commitBytes;
        }))->shouldBeCalled();

        $repoRoot->upsert(Argument::that(function (RepoRoot $root) use ($did): bool {
            return $root->getDid() === $did
                && $root->getCid() !== ''
                && $root->getRev() !== '';
        }))->shouldBeCalled();

        $store->getRepoBlocks()->willReturn($repoBlocks->reveal());
        $store->getRepoRoot()->willReturn($repoRoot->reveal());

        $initializer = new RepoInitializer($encoder);

        $result = $initializer->initialize($did, $store->reveal(), $signer);

        $expectedUnsigned = new UnsignedCommit(
            did: $did,
            data: new CidLink($mstCid),
            rev: $result['rev'],
            prev: null,
        );
        $expectedCommit = Commit::fromUnsigned($expectedUnsigned, new RecordingKeypair($signature), $encoder);

        $this->assertSame($encoder->encode($expectedUnsigned->toMap()), $signer->getLastSignedMessage());
        $this->assertSame($mstCid, $result['mstCid']);
        $this->assertSame($mstBytes, $result['mstBytes']);
        $this->assertSame($encoder->encode($expectedCommit->toMap()), $result['commitBytes']);
        $this->assertSame(CidUtil::computeForDagCbor($result['commitBytes']), $result['commitCid']);
    }
}
