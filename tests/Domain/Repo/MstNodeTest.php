<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Domain\Repo\MstLeaf;
use App\Domain\Repo\MstKey;
use App\Domain\Repo\MstMutation;
use App\Domain\Repo\MstNode;
use App\Domain\Repo\MstNodeEntry;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use InvalidArgumentException;
use Tests\TestCase;

class MstNodeTest extends TestCase
{
    public function testEmptyNodeHasExpectedShape(): void
    {
        $this->assertSame(
            [
                'l' => null,
                'e' => [],
            ],
            MstNode::empty()->toMap()
        );
    }

    public function testEncodeReturnsEncodedBytesAndMatchingCid(): void
    {
        $encoder = new NativeDagCborEncoder();

        [$bytes, $cid] = MstNode::empty()->encode($encoder);

        $this->assertSame($encoder->encode(MstNode::empty()->toMap()), $bytes);
        $this->assertSame(CidUtil::computeForDagCbor($bytes), $cid);
    }

    public function testDecodeRoundTripsEmptyNode(): void
    {
        $encoder = new NativeDagCborEncoder();
        $decoder = new NativeDagCborDecoder();

        [$bytes] = MstNode::empty()->encode($encoder);

        $node = MstNode::decode($decoder, $bytes);

        $this->assertTrue($node->isEmpty());
        $this->assertSame(MstNode::empty()->toMap(), $node->toMap());
    }

    public function testDecodeRoundTripsSerializedEntryShape(): void
    {
        $encoder = new NativeDagCborEncoder();
        $decoder = new NativeDagCborDecoder();
        $leftCid = CidUtil::computeForDagCbor("\xf6");
        $valueCid = CidUtil::computeForDagCbor("\xf4");
        $node = new MstNode(
            left: new CidLink($leftCid),
            entries: [
                new MstNodeEntry(
                    prefixLength: 16,
                    keySuffix: 'hi',
                    value: new CidLink($valueCid),
                    right: null,
                ),
            ],
        );

        [$bytes] = $node->encode($encoder);

        $decoded = MstNode::decode($decoder, $bytes);

        $this->assertEquals($node->toMap(), $decoded->toMap());
        $this->assertSame(1, $decoded->entryCount());
        $this->assertSame('hi', $decoded->getEntries()[0]->getKeySuffix());
    }

    public function testEntrySerializesKeySuffixAsCborBytes(): void
    {
        $valueCid = CidUtil::computeForDagCbor("\xf4");
        $entry = new MstNodeEntry(
            prefixLength: 3,
            keySuffix: 'rest',
            value: new CidLink($valueCid),
            right: null,
        );

        $this->assertEquals(
            [
                'p' => 3,
                'k' => new CborBytes('rest'),
                'v' => new CidLink($valueCid),
                't' => null,
            ],
            $entry->toMap()
        );
        $this->assertSame('prerest', $entry->expandKey('prefix-xyz'));
    }

    public function testCompressesLeavesIntoPrefixEncodedEntries(): void
    {
        $firstCid = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $secondCid = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $firstKey = 'app.bsky.feed.post/abcdefg';
        $secondKey = 'app.bsky.feed.post/abcdehi';
        $sharedPrefix = MstKey::countSharedPrefix($firstKey, $secondKey);

        $node = MstNode::fromLeaves(null, [
            new MstLeaf($firstKey, $firstCid, null),
            new MstLeaf($secondKey, $secondCid, null),
        ]);

        $this->assertEquals(
            [
                'l' => null,
                'e' => [
                    [
                        'p' => 0,
                        'k' => new CborBytes($firstKey),
                        'v' => $firstCid,
                        't' => null,
                    ],
                    [
                        'p' => $sharedPrefix,
                        'k' => new CborBytes('hi'),
                        'v' => $secondCid,
                        't' => null,
                    ],
                ],
            ],
            $node->toMap()
        );
    }

    public function testExpandsPrefixEncodedEntriesIntoLeaves(): void
    {
        $firstCid = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $secondCid = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $firstKey = 'app.bsky.feed.post/abcdefg';
        $secondKey = 'app.bsky.feed.post/abcdehi';
        $node = new MstNode(
            left: null,
            entries: [
                new MstNodeEntry(0, $firstKey, $firstCid, null),
                new MstNodeEntry(MstKey::countSharedPrefix($firstKey, $secondKey), 'hi', $secondCid, null),
            ],
        );

        $leaves = $node->toLeaves();

        $this->assertCount(2, $leaves);
        $this->assertSame($firstKey, $leaves[0]->getKey());
        $this->assertSame($secondKey, $leaves[1]->getKey());
        $this->assertSame($secondCid->getCid(), $leaves[1]->getValue()->getCid());
    }

    public function testGetsLeafByExactKey(): void
    {
        $firstCid = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $secondCid = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/abcdefg', $firstCid, null),
            new MstLeaf('app.bsky.feed.post/abcdehi', $secondCid, null),
        ]);

        $leaf = $node->getLeaf('app.bsky.feed.post/abcdehi');

        $this->assertNotNull($leaf);
        $this->assertSame('app.bsky.feed.post/abcdehi', $leaf->getKey());
        $this->assertSame($secondCid->getCid(), $leaf->getValue()->getCid());
        $this->assertTrue($node->hasLeaf('app.bsky.feed.post/abcdefg'));
        $this->assertFalse($node->hasLeaf('app.bsky.feed.post/not-found'));
    }

    public function testListsLeavesInSortedOrderWithBounds(): void
    {
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf4")), null),
            new MstLeaf('app.bsky.feed.post/bbb', new CidLink(CidUtil::computeForDagCbor("\xf5")), null),
            new MstLeaf('app.bsky.feed.post/ccc', new CidLink(CidUtil::computeForDagCbor("\xf6")), null),
        ]);

        $listed = $node->listLeaves(2, after: 'app.bsky.feed.post/aaa', before: 'app.bsky.feed.post/ddd');

        $this->assertCount(2, $listed);
        $this->assertSame('app.bsky.feed.post/bbb', $listed[0]->getKey());
        $this->assertSame('app.bsky.feed.post/ccc', $listed[1]->getKey());
        $this->assertSame([], $node->listLeaves(0));
    }

    public function testListsLeavesByPrefix(): void
    {
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf4")), null),
            new MstLeaf('app.bsky.feed.post/bbb', new CidLink(CidUtil::computeForDagCbor("\xf5")), null),
            new MstLeaf('app.bsky.graph.follow/aaa', new CidLink(CidUtil::computeForDagCbor("\xf6")), null),
        ]);

        $listed = $node->listLeavesWithPrefix('app.bsky.feed.post/', 5);

        $this->assertCount(2, $listed);
        $this->assertSame('app.bsky.feed.post/aaa', $listed[0]->getKey());
        $this->assertSame('app.bsky.feed.post/bbb', $listed[1]->getKey());
    }

    public function testAddLeafInsertsInSortedOrder(): void
    {
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf4")), null),
            new MstLeaf('app.bsky.feed.post/ccc', new CidLink(CidUtil::computeForDagCbor("\xf6")), null),
        ]);

        $updated = $node->addLeaf(
            new MstLeaf('app.bsky.feed.post/bbb', new CidLink(CidUtil::computeForDagCbor("\xf5")), null)
        );

        $this->assertSame(
            ['app.bsky.feed.post/aaa', 'app.bsky.feed.post/bbb', 'app.bsky.feed.post/ccc'],
            array_map(static fn (MstLeaf $leaf): string => $leaf->getKey(), $updated->toLeaves())
        );
    }

    public function testUpdateLeafReplacesExistingValue(): void
    {
        $oldCid = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $newCid = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', $oldCid, null),
        ]);

        $updated = $node->updateLeaf('app.bsky.feed.post/aaa', $newCid);

        $this->assertSame($newCid->getCid(), $updated->getLeaf('app.bsky.feed.post/aaa')?->getValue()->getCid());
    }

    public function testPutLeafUpsertsByKey(): void
    {
        $initial = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $replacement = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', $initial, null),
        ]);

        $updated = $node->putLeaf(new MstLeaf('app.bsky.feed.post/aaa', $replacement, null));
        $expanded = $updated->putLeaf(new MstLeaf('app.bsky.feed.post/bbb', $initial, null));

        $this->assertSame($replacement->getCid(), $updated->getLeaf('app.bsky.feed.post/aaa')?->getValue()->getCid());
        $this->assertCount(2, $expanded->toLeaves());
    }

    public function testDeleteLeafRemovesExistingKey(): void
    {
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf4")), null),
            new MstLeaf('app.bsky.feed.post/bbb', new CidLink(CidUtil::computeForDagCbor("\xf5")), null),
        ]);

        $updated = $node->deleteLeaf('app.bsky.feed.post/aaa');

        $this->assertFalse($updated->hasLeaf('app.bsky.feed.post/aaa'));
        $this->assertSame(
            ['app.bsky.feed.post/bbb'],
            array_map(static fn (MstLeaf $leaf): string => $leaf->getKey(), $updated->toLeaves())
        );
    }

    public function testAddAndDeleteThrowForInvalidMutationTargets(): void
    {
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf4")), null),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There is already a value at key: app.bsky.feed.post/aaa');

        $node->addLeaf(new MstLeaf('app.bsky.feed.post/aaa', new CidLink(CidUtil::computeForDagCbor("\xf5")), null));
    }

    public function testDeleteLeafThrowsForMissingKey(): void
    {
        $node = MstNode::empty();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find a record with key: app.bsky.feed.post/missing');

        $node->deleteLeaf('app.bsky.feed.post/missing');
    }

    public function testApplyMutationsSupportsPutAndDeleteBatch(): void
    {
        $initial = new CidLink(CidUtil::computeForDagCbor("\xf4"));
        $replacement = new CidLink(CidUtil::computeForDagCbor("\xf5"));
        $newValue = new CidLink(CidUtil::computeForDagCbor("\xf6"));
        $node = MstNode::fromLeaves(null, [
            new MstLeaf('app.bsky.feed.post/aaa', $initial, null),
            new MstLeaf('app.bsky.feed.post/bbb', $initial, null),
        ]);

        $updated = $node->applyMutations([
            MstMutation::put('app.bsky.feed.post/aaa', $replacement),
            MstMutation::delete('app.bsky.feed.post/bbb'),
            MstMutation::put('app.bsky.feed.post/ccc', $newValue),
        ]);

        $this->assertSame($replacement->getCid(), $updated->getLeaf('app.bsky.feed.post/aaa')?->getValue()->getCid());
        $this->assertFalse($updated->hasLeaf('app.bsky.feed.post/bbb'));
        $this->assertSame(
            ['app.bsky.feed.post/aaa', 'app.bsky.feed.post/ccc'],
            array_map(static fn (MstLeaf $leaf): string => $leaf->getKey(), $updated->toLeaves())
        );
    }
}
