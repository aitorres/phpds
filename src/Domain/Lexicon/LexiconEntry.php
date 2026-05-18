<?php

declare(strict_types=1);

namespace App\Domain\Lexicon;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Cached lexicon document fetched from an OAuth client.
 * Maps to the `lexicon` table in the reference TS PDS.
 */
class LexiconEntry implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $lexicon  Parsed lexicon JSON document.
     */
    public function __construct(
        private readonly string $nsid,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $lastSucceededAt,
        private readonly ?string $uri,
        private readonly ?array $lexicon,
    ) {
    }

    public function getNsid(): string
    {
        return $this->nsid;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastSucceededAt(): ?DateTimeImmutable
    {
        return $this->lastSucceededAt;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLexicon(): ?array
    {
        return $this->lexicon;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'nsid'            => $this->nsid,
            'createdAt'       => $this->createdAt->format(DATE_ATOM),
            'updatedAt'       => $this->updatedAt->format(DATE_ATOM),
            'lastSucceededAt' => $this->lastSucceededAt?->format(DATE_ATOM),
            'uri'             => $this->uri,
        ];
    }
}
