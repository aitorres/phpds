<?php

declare(strict_types=1);

namespace App\Domain\Sequencer;

use App\Domain\Repo\CarWriter;
use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\DagCborEncoder;
use DateTimeImmutable;

/**
 * Builds DAG-CBOR payloads for `com.atproto.sync.subscribeRepos` events.
 *
 * Each method returns the raw bytes ready to be passed to
 * {@see SequencerRepository::append}.
 *
 * Payload shape:
 *   - #commit:   {seq, rebase, tooBig, repo, commit, prev, rev, since, blocks, ops, blobs, time}
 *   - #identity: {seq, did, time, handle?}
 *   - #account:  {seq, did, time, active, status?}
 */
final class SubscribeReposEventFactory
{
    public function __construct(
        private readonly DagCborEncoder $cbor,
        private readonly CarWriter $cars,
    ) {
    }

    /**
     * Build the CBOR-encoded body of a #commit event for a brand-new
     * (genesis) repo: prev=null, since=null, no ops, no blobs, CAR
     * containing just the commit + empty-MST blocks.
     *
     * @param array<string, string> $blocks  CID -> raw block bytes
     */
    public function genesisCommit(
        string $did,
        string $commitCid,
        string $rev,
        array $blocks,
        DateTimeImmutable $time,
    ): string {
        $car = $this->cars->write([$commitCid], $blocks);

        return $this->cbor->encode([
            'rebase' => false,
            'tooBig' => false,
            'repo'   => $did,
            'commit' => new CidLink($commitCid),
            'prev'   => null,
            'rev'    => $rev,
            'since'  => null,
            'blocks' => new CborBytes($car),
            'ops'    => [],
            'blobs'  => [],
            'time'   => $time->format('Y-m-d\TH:i:s.v\Z'),
        ]);
    }

    public function identity(string $did, string $handle, DateTimeImmutable $time): string
    {
        return $this->cbor->encode([
            'did'    => $did,
            'time'   => $time->format('Y-m-d\TH:i:s.v\Z'),
            'handle' => $handle,
        ]);
    }

    public function account(
        string $did,
        bool $active,
        DateTimeImmutable $time,
        ?string $status = null,
    ): string {
        $payload = [
            'did'    => $did,
            'time'   => $time->format('Y-m-d\TH:i:s.v\Z'),
            'active' => $active,
        ];
        if ($status !== null) {
            $payload['status'] = $status;
        }
        return $this->cbor->encode($payload);
    }
}
