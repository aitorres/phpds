<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Sync;

use JsonSerializable;

/**
 * Per-repo view returned by `com.atproto.sync.listRepos`.
 */
class RepoView implements JsonSerializable
{
    public const STATUS_TAKENDOWN      = 'takendown';
    public const STATUS_SUSPENDED      = 'suspended';
    public const STATUS_DELETED        = 'deleted';
    public const STATUS_DEACTIVATED    = 'deactivated';
    public const STATUS_DESYNCHRONIZED = 'desynchronized';
    public const STATUS_THROTTLED      = 'throttled';

    public function __construct(
        private readonly string $did,
        private readonly string $head,
        private readonly string $rev,
        private readonly bool $active,
        private readonly ?string $status = null,
    ) {
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getHead(): string
    {
        return $this->head;
    }

    public function getRev(): string
    {
        return $this->rev;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $out = [
            'did'    => $this->did,
            'head'   => $this->head,
            'rev'    => $this->rev,
            'active' => $this->active,
        ];
        if ($this->status !== null) {
            $out['status'] = $this->status;
        }
        return $out;
    }
}
