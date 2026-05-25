<?php

declare(strict_types=1);

namespace App\Domain\Account;

final class AccountCreationResult
{
    /**
     * @param array<string, mixed>|null $didDoc
     */
    public function __construct(
        private readonly string $did,
        private readonly string $handle,
        private readonly ?array $didDoc,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHandle(): string
    {
        return $this->handle;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDidDoc(): ?array
    {
        return $this->didDoc;
    }
}
