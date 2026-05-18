<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;
use JsonSerializable;

/**
 * OAuth token (issued after authorization). Maps to the `token` table.
 *
 * JSON-typed columns (clientAuth, parameters, details) are held as
 * associative arrays; serialization/validation is deferred to the OAuth
 * endpoint layer.
 */
class OAuthToken implements JsonSerializable
{
    /**
     * @param array<string, mixed>      $clientAuth
     * @param array<string, mixed>      $parameters
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        private readonly int $id,
        private readonly string $did,
        private readonly string $tokenId,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly DateTimeImmutable $expiresAt,
        private readonly string $clientId,
        private readonly array $clientAuth,
        private readonly ?string $deviceId,
        private readonly array $parameters,
        private readonly ?array $details,
        private readonly ?string $code,
        private readonly ?string $currentRefreshToken,
        private readonly ?string $scope,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getTokenId(): string
    {
        return $this->tokenId;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientAuth(): array
    {
        return $this->clientAuth;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getCurrentRefreshToken(): ?string
    {
        return $this->currentRefreshToken;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'                   => $this->id,
            'did'                  => $this->did,
            'tokenId'              => $this->tokenId,
            'createdAt'            => $this->createdAt->format(DATE_ATOM),
            'updatedAt'            => $this->updatedAt->format(DATE_ATOM),
            'expiresAt'            => $this->expiresAt->format(DATE_ATOM),
            'clientId'             => $this->clientId,
            'deviceId'             => $this->deviceId,
            'code'                 => $this->code,
            'currentRefreshToken'  => $this->currentRefreshToken,
            'scope'                => $this->scope,
        ];
    }
}
