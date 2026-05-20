<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Server;

use JsonSerializable;

class CreateInviteCodeResponse implements JsonSerializable
{
    public function __construct(private readonly string $code)
    {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return ['code' => $this->code];
    }
}
