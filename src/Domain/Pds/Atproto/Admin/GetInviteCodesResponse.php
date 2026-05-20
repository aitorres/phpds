<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Admin;

use JsonSerializable;

class GetInviteCodesResponse implements JsonSerializable
{
    /** @var list<InviteCodeView> */
    private array $codes;

    /**
     * @param list<InviteCodeView> $codes
     */
    public function __construct(array $codes, private readonly ?string $cursor = null)
    {
        $this->codes = $codes;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $out = ['codes' => $this->codes];
        if ($this->cursor !== null) {
            $out['cursor'] = $this->cursor;
        }

        return $out;
    }
}
