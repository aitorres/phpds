<?php

declare(strict_types=1);

namespace App\Domain\Pds\Atproto\Sync;

use JsonSerializable;

class ListReposResponse implements JsonSerializable
{
    /** @var list<RepoView> */
    private array $repos;

    private ?string $cursor;

    /**
     * @param list<RepoView> $repos
     */
    public function __construct(array $repos, ?string $cursor = null)
    {
        $this->repos = $repos;
        $this->cursor = $cursor;
    }

    /**
     * @return list<RepoView>
     */
    public function getRepos(): array
    {
        return $this->repos;
    }

    public function getCursor(): ?string
    {
        return $this->cursor;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        $out = ['repos' => $this->repos];
        if ($this->cursor !== null) {
            $out['cursor'] = $this->cursor;
        }
        return $out;
    }
}
