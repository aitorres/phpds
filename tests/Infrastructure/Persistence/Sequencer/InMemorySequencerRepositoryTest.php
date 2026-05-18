<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Sequencer;

use App\Domain\Sequencer\RepoSeqEvent;
use App\Infrastructure\Persistence\Sequencer\InMemorySequencerRepository;
use Tests\TestCase;

class InMemorySequencerRepositoryTest extends TestCase
{
    public function testAppendReturnsMonotonicallyIncreasingSeq(): void
    {
        $repo = new InMemorySequencerRepository();

        $seq1 = $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'event1');
        $seq2 = $repo->append('did:plc:alice', RepoSeqEvent::TYPE_IDENTITY, 'event2');
        $seq3 = $repo->append('did:plc:bob', RepoSeqEvent::TYPE_ACCOUNT, 'event3');

        $this->assertSame(1, $seq1);
        $this->assertSame(2, $seq2);
        $this->assertSame(3, $seq3);
    }

    public function testLatestSeqReturnsNullWhenEmpty(): void
    {
        $repo = new InMemorySequencerRepository();
        $this->assertNull($repo->latestSeq());
    }

    public function testLatestSeqReturnsHighestSeq(): void
    {
        $repo = new InMemorySequencerRepository();
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e1');
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e2');

        $this->assertSame(2, $repo->latestSeq());
    }

    public function testReadRangeReturnsEventsAfterGivenSeq(): void
    {
        $repo = new InMemorySequencerRepository();
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e1'); // seq=1
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e2'); // seq=2
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e3'); // seq=3

        $results = $repo->readRange(1);
        $this->assertCount(2, $results);
        $this->assertSame(2, $results[0]->getSeq());
        $this->assertSame(3, $results[1]->getSeq());
    }

    public function testReadRangeRespectsLimit(): void
    {
        $repo = new InMemorySequencerRepository();
        for ($i = 0; $i < 10; $i++) {
            $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, "e{$i}");
        }

        $results = $repo->readRange(0, 5);
        $this->assertCount(5, $results);
    }

    public function testInvalidateForDidMarksEvents(): void
    {
        $repo = new InMemorySequencerRepository();
        $repo->append('did:plc:alice', RepoSeqEvent::TYPE_APPEND, 'e1');
        $repo->append('did:plc:bob', RepoSeqEvent::TYPE_APPEND, 'e2');
        $repo->invalidateForDid('did:plc:alice');

        $events = $repo->readRange(0);
        $aliceEvent = array_values(array_filter($events, fn($e) => $e->getDid() === 'did:plc:alice'))[0];
        $bobEvent   = array_values(array_filter($events, fn($e) => $e->getDid() === 'did:plc:bob'))[0];

        $this->assertTrue($aliceEvent->isInvalidated());
        $this->assertFalse($bobEvent->isInvalidated());
    }

    public function testReadRangeResultsAreInAscendingOrder(): void
    {
        $repo = new InMemorySequencerRepository();
        $repo->append('did:plc:a', RepoSeqEvent::TYPE_APPEND, 'e1');
        $repo->append('did:plc:b', RepoSeqEvent::TYPE_APPEND, 'e2');
        $repo->append('did:plc:c', RepoSeqEvent::TYPE_APPEND, 'e3');

        $results = $repo->readRange(0);
        $seqs = array_map(fn($e) => $e->getSeq(), $results);
        $sorted = $seqs;
        sort($sorted);
        $this->assertSame($sorted, $seqs);
    }
}
