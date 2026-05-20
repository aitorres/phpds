<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Admin;

use JsonSerializable;

class InviteCodeView implements JsonSerializable
{
    /** @var list<InviteCodeUseView> */
    private array $uses;

    /**
     * @param list<InviteCodeUseView> $uses
     */
    public function __construct(
        private readonly string $code,
        private readonly int $available,
        private readonly bool $disabled,
        private readonly string $forAccount,
        private readonly string $createdBy,
        private readonly string $createdAt,
        array $uses,
    ) {
        $this->uses = $uses;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'available' => $this->available,
            'disabled' => $this->disabled,
            'forAccount' => $this->forAccount,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'uses' => $this->uses,
        ];
    }
}
