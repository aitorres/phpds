<?php

declare(strict_types=1);

namespace App\Domain\Sequencer;

interface SequencerRepository
{
    /**
     * Append a new event to the log, assigning the next sequence number.
     *
     * @return int  The sequence number assigned to the appended event.
     */
    public function append(
        string $did,
        string $eventType,
        string $event,
    ): int;

    /**
     * @return int|null  The highest sequence number, or null if the log is empty.
     */
    public function latestSeq(): ?int;

    /**
     * Read a range of events after a given sequence number.
     *
     * @return RepoSeqEvent[]  In ascending sequence order.
     */
    public function readRange(int $afterSeq, int $limit = 500): array;

    /**
     * Mark all events for a given DID as invalidated (e.g. on account deletion).
     */
    public function invalidateForDid(string $did): void;
}
