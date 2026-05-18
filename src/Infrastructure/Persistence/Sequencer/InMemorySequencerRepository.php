<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Sequencer;

use App\Domain\Sequencer\RepoSeqEvent;
use App\Domain\Sequencer\SequencerRepository;
use DateTimeImmutable;

class InMemorySequencerRepository implements SequencerRepository
{
    /** @var RepoSeqEvent[] ordered by seq ascending */
    private array $events = [];

    private int $counter;

    /**
     * @param RepoSeqEvent[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        $this->counter = 0;
        foreach ($seeds as $event) {
            $this->events[] = $event;
            if ($event->getSeq() > $this->counter) {
                $this->counter = $event->getSeq();
            }
        }
    }

    public function append(string $did, string $eventType, string $event): int
    {
        $seq = ++$this->counter;

        $this->events[] = new RepoSeqEvent(
            seq: $seq,
            did: $did,
            eventType: $eventType,
            event: $event,
            sequencedAt: new DateTimeImmutable(),
        );

        return $seq;
    }

    public function latestSeq(): ?int
    {
        if (empty($this->events)) {
            return null;
        }

        return max(array_map(fn(RepoSeqEvent $e) => $e->getSeq(), $this->events));
    }

    /**
     * @return RepoSeqEvent[]
     */
    public function readRange(int $afterSeq, int $limit = 500): array
    {
        $results = array_filter(
            $this->events,
            fn(RepoSeqEvent $e) => $e->getSeq() > $afterSeq,
        );

        usort($results, fn(RepoSeqEvent $a, RepoSeqEvent $b) => $a->getSeq() <=> $b->getSeq());

        return array_slice($results, 0, $limit);
    }

    public function invalidateForDid(string $did): void
    {
        $this->events = array_map(function (RepoSeqEvent $e) use ($did): RepoSeqEvent {
            if ($e->getDid() !== $did || $e->isInvalidated()) {
                return $e;
            }

            return new RepoSeqEvent(
                seq: $e->getSeq(),
                did: $e->getDid(),
                eventType: $e->getEventType(),
                event: $e->getEvent(),
                sequencedAt: $e->getSequencedAt(),
                invalidated: true,
            );
        }, $this->events);
    }
}
