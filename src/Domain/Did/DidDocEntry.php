<?php

declare(strict_types=1);

namespace App\Domain\Did;

use DateTimeImmutable;
use JsonSerializable;

/**
 * A cached DID document entry.
 * Mirrors the `did_doc` table from the reference TS DID-cache schema.
 */
class DidDocEntry implements JsonSerializable
{
    /**
     * @param array<string, mixed> $doc  Parsed DID document.
     */
    public function __construct(
        private readonly string $did,
        private readonly array $doc,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDoc(): array
    {
        return $this->doc;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did'       => $this->did,
            'doc'       => $this->doc,
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
