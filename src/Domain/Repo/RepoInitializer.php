<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use App\Domain\ActorStore\ActorStore;
use App\Domain\Common\Tid;
use App\Domain\Crypto\Keypair;
use DateTimeImmutable;

/**
 * Builds and persists the genesis commit for a freshly-created actor.
 *
 * Steps:
 *   1. Encode the empty MST -> CID + CBOR block.
 *   2. Build an UnsignedCommit pointing at the empty MST CID.
 *   3. Sign the CBOR encoding of the unsigned commit with the actor's
 *      signing keypair (secp256k1, low-S).
 *   4. Compute the commit CID; store the commit + empty MST as repo_blocks;
 *      upsert repo_root.
 */
final class RepoInitializer
{
    public function __construct(
        private readonly DagCborEncoder $cborEncoder,
    ) {
    }

    /**
     * @return array{commitCid: string, commitBytes: string, mstCid: string, mstBytes: string, rev: string}
     */
    public function initialize(string $did, ActorStore $store, Keypair $signer): array
    {
        $rev = Tid::next();

        [$mstBytes, $mstCid] = EmptyMst::encode($this->cborEncoder);

        $unsigned = new UnsignedCommit(
            did: $did,
            data: new CidLink($mstCid),
            rev: $rev,
            prev: null,
        );
        $commit       = Commit::fromUnsigned($unsigned, $signer, $this->cborEncoder);
        $commitBytes  = $this->cborEncoder->encode($commit->toMap());
        $commitCid    = CidUtil::computeForDagCbor($commitBytes);

        $blocks = $store->getRepoBlocks();

        // the data block containing the empty MST
        $blocks->save(new RepoBlock(
            cid: $mstCid,
            repoRev: $rev,
            size: strlen($mstBytes),
            content: $mstBytes,
        ));

        // the commit block that points to the MST,
        // this is the actual repo root!
        $blocks->save(new RepoBlock(
            cid: $commitCid,
            repoRev: $rev,
            size: strlen($commitBytes),
            content: $commitBytes,
        ));

        $store->getRepoRoot()->upsert(new RepoRoot(
            did: $did,
            cid: $commitCid,
            rev: $rev,
            indexedAt: new DateTimeImmutable(),
        ));

        return [
            'commitCid'   => $commitCid,
            'commitBytes' => $commitBytes,
            'mstCid'      => $mstCid,
            'mstBytes'    => $mstBytes,
            'rev'         => $rev,
        ];
    }
}
