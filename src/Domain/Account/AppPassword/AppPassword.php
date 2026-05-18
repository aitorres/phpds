<?php

declare(strict_types=1);

namespace App\Domain\Account\AppPassword;

use DateTimeImmutable;
use JsonSerializable;

class AppPassword implements JsonSerializable
{
    public function __construct(
        private readonly string $did,
        private readonly string $name,
        private readonly string $passwordScrypt,
        private readonly DateTimeImmutable $createdAt,
        private readonly bool $privileged = false,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPasswordScrypt(): string
    {
        return $this->passwordScrypt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isPrivileged(): bool
    {
        return $this->privileged;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did'        => $this->did,
            'name'       => $this->name,
            'createdAt'  => $this->createdAt->format(DATE_ATOM),
            'privileged' => $this->privileged,
        ];
    }
}
