<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Sync;

use JsonSerializable;

class GetLatestCommitResponse implements JsonSerializable
{
    public function __construct(
        private readonly string $cid,
        private readonly string $rev,
    ) {
    }

    public function getCid(): string
    {
        return $this->cid;
    }

    public function getRev(): string
    {
        return $this->rev;
    }

    /**
     * @return array{cid: string, rev: string}
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'cid' => $this->cid,
            'rev' => $this->rev,
        ];
    }
}
