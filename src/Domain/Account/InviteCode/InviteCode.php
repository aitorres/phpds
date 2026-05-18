<?php

declare(strict_types=1);

namespace App\Domain\Account\InviteCode;

use DateTimeImmutable;
use JsonSerializable;

class InviteCode implements JsonSerializable
{
    public function __construct(
        private readonly string $code,
        private readonly int $availableUses,
        private readonly bool $disabled,
        private readonly string $forAccount,
        private readonly string $createdBy,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getAvailableUses(): int
    {
        return $this->availableUses;
    }

    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    public function getForAccount(): string
    {
        return $this->forAccount;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'code'          => $this->code,
            'availableUses' => $this->availableUses,
            'disabled'      => $this->disabled,
            'forAccount'    => $this->forAccount,
            'createdBy'     => $this->createdBy,
            'createdAt'     => $this->createdAt->format(DATE_ATOM),
        ];
    }
}
