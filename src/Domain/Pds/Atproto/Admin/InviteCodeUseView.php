<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Admin;

use JsonSerializable;

class InviteCodeUseView implements JsonSerializable
{
    public function __construct(
        private readonly string $usedBy,
        private readonly string $usedAt,
    ) {
    }

    /**
     * @return array<string, string>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'usedBy' => $this->usedBy,
            'usedAt' => $this->usedAt,
        ];
    }
}
