<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Represents a browser/device session for OAuth.
 * Maps to the `device` table in the reference TS PDS.
 */
class Device implements JsonSerializable
{
    public function __construct(
        private readonly string $id,
        private readonly string $sessionId,
        private readonly ?string $userAgent,
        private readonly string $ipAddress,
        private readonly DateTimeImmutable $lastSeenAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'          => $this->id,
            'sessionId'   => $this->sessionId,
            'userAgent'   => $this->userAgent,
            'ipAddress'   => $this->ipAddress,
            'lastSeenAt'  => $this->lastSeenAt->format(DATE_ATOM),
        ];
    }
}
