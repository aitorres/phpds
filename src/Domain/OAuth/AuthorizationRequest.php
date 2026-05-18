<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Pending OAuth authorization request. Maps to `authorization_request` table.
 *
 * @phpstan-type ClientAuthData array<string, mixed>|null
 * @phpstan-type ParametersData array<string, mixed>
 */
class AuthorizationRequest implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $clientAuth
     * @param array<string, mixed>      $parameters
     */
    public function __construct(
        private readonly string $id,
        private readonly ?string $did,
        private readonly ?string $deviceId,
        private readonly string $clientId,
        private readonly ?array $clientAuth,
        private readonly array $parameters,
        private readonly DateTimeImmutable $expiresAt,
        private readonly ?string $code,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDid(): ?string
    {
        return $this->did;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getClientAuth(): ?array
    {
        return $this->clientAuth;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'id'       => $this->id,
            'did'      => $this->did,
            'deviceId' => $this->deviceId,
            'clientId' => $this->clientId,
            'expiresAt' => $this->expiresAt->format(DATE_ATOM),
            'code'     => $this->code,
        ];
    }
}
