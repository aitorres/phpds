<?php

declare(strict_types=1);

namespace App\Domain\Record;

use JsonSerializable;

/**
 * A reverse-reference from a record field path to the AT URI it points at.
 * Maps to the `backlink` table in the actor-store schema.
 *
 * Lives inside an ActorStore scoped to a specific DID, so no `did` field here.
 */
class Backlink implements JsonSerializable
{
    public function __construct(
        private readonly string $uri,
        private readonly string $path,
        private readonly string $linkTo,
    ) {
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getLinkTo(): string
    {
        return $this->linkTo;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'uri'    => $this->uri,
            'path'   => $this->path,
            'linkTo' => $this->linkTo,
        ];
    }
}
