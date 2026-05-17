<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Identity;

use JsonSerializable;

class ResolveHandleResponse implements JsonSerializable
{
    private string $did;

    public function __construct(string $did)
    {
        $this->did = $did;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    /**
     * @return array{did: string}
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did' => $this->did,
        ];
    }
}
