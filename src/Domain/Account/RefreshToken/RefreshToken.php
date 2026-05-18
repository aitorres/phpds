<?php

declare(strict_types=1);

namespace App\Domain\Account\RefreshToken;

use JsonSerializable;

/**
 * Non-OAuth refresh token used for com.atproto.server session management.
 */
class RefreshToken implements JsonSerializable
{
    public function __construct(
        private readonly string $id,
        private readonly string $did,
        private readonly string $expiresAt,
        private readonly ?string $appPasswordName,
        private readonly ?string $nextId,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getExpiresAt(): string
    {
        return $this->expiresAt;
    }

    public function getAppPasswordName(): ?string
    {
        return $this->appPasswordName;
    }

    public function getNextId(): ?string
    {
        return $this->nextId;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'              => $this->id,
            'did'             => $this->did,
            'expiresAt'       => $this->expiresAt,
            'appPasswordName' => $this->appPasswordName,
            'nextId'          => $this->nextId,
        ];
    }
}
