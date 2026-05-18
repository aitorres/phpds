<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Records that a DID has authorized a specific OAuth client.
 * Maps to the `authorized_client` table.
 */
class AuthorizedClient implements JsonSerializable
{
    /**
     * @param array<string, mixed> $data  Opaque client-specific authorization data.
     */
    public function __construct(
        private readonly string $did,
        private readonly string $clientId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly array $data,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did'       => $this->did,
            'clientId'  => $this->clientId,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
