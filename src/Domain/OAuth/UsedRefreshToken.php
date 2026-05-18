<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use JsonSerializable;

/**
 * Records used refresh tokens for replay prevention.
 * Maps to the `used_refresh_token` table.
 */
class UsedRefreshToken implements JsonSerializable
{
    public function __construct(
        private readonly int $tokenId,
        private readonly string $refreshToken,
    ) {
    }

    public function getTokenId(): int
    {
        return $this->tokenId;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'tokenId'      => $this->tokenId,
            'refreshToken' => $this->refreshToken,
        ];
    }
}
