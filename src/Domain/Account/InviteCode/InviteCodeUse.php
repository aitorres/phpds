<?php

declare(strict_types=1);

namespace App\Domain\Account\InviteCode;

use DateTimeImmutable;
use JsonSerializable;

class InviteCodeUse implements JsonSerializable
{
    public function __construct(
        private readonly string $code,
        private readonly string $usedBy,
        private readonly DateTimeImmutable $usedAt,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getUsedBy(): string
    {
        return $this->usedBy;
    }

    public function getUsedAt(): DateTimeImmutable
    {
        return $this->usedAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'code'   => $this->code,
            'usedBy' => $this->usedBy,
            'usedAt' => $this->usedAt->format(DATE_ATOM),
        ];
    }
}
