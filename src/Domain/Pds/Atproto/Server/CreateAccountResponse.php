<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Server;

use JsonSerializable;

final class CreateAccountResponse implements JsonSerializable
{
    /**
     * @param array<string, mixed>|null $didDoc
     */
    public function __construct(
        private readonly string $accessJwt,
        private readonly string $refreshJwt,
        private readonly string $handle,
        private readonly string $did,
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

    public function getHandle(): string
    {
        return $this->handle;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDidDoc(): ?array
    {
        return $this->didDoc;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $payload = [
            'accessJwt'  => $this->accessJwt,
            'refreshJwt' => $this->refreshJwt,
            'handle'     => $this->handle,
            'did'        => $this->did,
        ];
        if ($this->didDoc !== null) {
            $payload['didDoc'] = $this->didDoc;
        }
        return $payload;
    }
}
