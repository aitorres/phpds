<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Sync;

use JsonSerializable;

/**
 * Response body for `com.atproto.sync.getRepoStatus`.
 */
class GetRepoStatusResponse implements JsonSerializable
{
    public function __construct(
        private readonly string $did,
        private readonly bool $active,
        private readonly ?string $status = null,
        private readonly ?string $rev = null,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getRev(): ?string
    {
        return $this->rev;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $out = [
            'did'    => $this->did,
            'active' => $this->active,
        ];
        if ($this->status !== null) {
            $out['status'] = $this->status;
        }
        if ($this->rev !== null) {
            $out['rev'] = $this->rev;
        }
        return $out;
    }
}
