<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Server;

use JsonSerializable;

final class CreateSessionResponse implements JsonSerializable
{
    public const HANDLE_INVALID = 'handle.invalid';

    public function __construct(
        private readonly string $accessJwt,
        private readonly string $refreshJwt,
        private readonly string $did,
        private readonly string $handle,
        private readonly ?string $email,
        private readonly bool $emailConfirmed,
        private readonly bool $active,
        private readonly ?string $status,
        /** @var array<string, mixed>|null */
        private readonly ?array $didDoc = null,
    ) {
    }

    public function getAccessJwt(): string
    {
        return $this->accessJwt;
    }

    public function getRefreshJwt(): string
    {
        return $this->refreshJwt;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function isEmailConfirmed(): bool
    {
        return $this->emailConfirmed;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDidDoc(): ?array
    {
        return $this->didDoc;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $payload = [
            'accessJwt'      => $this->accessJwt,
            'refreshJwt'     => $this->refreshJwt,
            'handle'         => $this->handle,
            'did'            => $this->did,
            'emailConfirmed' => $this->emailConfirmed,
            'active'         => $this->active,
        ];
        if ($this->email !== null) {
            $payload['email'] = $this->email;
        }
        if ($this->didDoc !== null) {
            $payload['didDoc'] = $this->didDoc;
        }
        if ($this->status !== null) {
            $payload['status'] = $this->status;
        }
        return $payload;
    }
}
