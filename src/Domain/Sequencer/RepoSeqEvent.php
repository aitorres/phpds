<?php

declare(strict_types=1);

namespace App\Domain\Sequencer;

use DateTimeImmutable;
use JsonSerializable;

/**
 * A single event in the firehose event log.
 * Maps to the `repo_seq` table in the reference TS sequencer schema.
 *
 * `event` holds raw CBOR-encoded bytes (stored as a binary string).
 * Callers are responsible for encoding via {@see \App\Infrastructure\Repo\NativeDagCborEncoder}
 * before constructing this object.
 */
class RepoSeqEvent implements JsonSerializable
{
    public const TYPE_APPEND    = 'append';
    public const TYPE_SYNC      = 'sync';
    public const TYPE_IDENTITY  = 'identity';
    public const TYPE_ACCOUNT   = 'account';
    public const TYPE_TOMBSTONE = 'tombstone';

    public function __construct(
        private readonly int $seq,
        private readonly string $did,
        private readonly string $eventType,
        private readonly string $event,
        private readonly DateTimeImmutable $sequencedAt,
        private readonly bool $invalidated = false,
    ) {
    }

    public function getSeq(): int
    {
        return $this->seq;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getSequencedAt(): DateTimeImmutable
    {
        return $this->sequencedAt;
    }

    public function isInvalidated(): bool
    {
        return $this->invalidated;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'seq'          => $this->seq,
            'did'          => $this->did,
            'eventType'    => $this->eventType,
            'sequencedAt'  => $this->sequencedAt->format(DATE_ATOM),
            'invalidated'  => $this->invalidated,
        ];
    }
}
