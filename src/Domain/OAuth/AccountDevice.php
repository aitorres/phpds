<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Maps a DID to a device session (OAuth).
 * Corresponds to the `account_device` table in the reference TS PDS.
 */
class AccountDevice implements JsonSerializable
{
    public function __construct(
        private readonly string $did,
        private readonly string $deviceId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
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
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did'       => $this->did,
            'deviceId'  => $this->deviceId,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
