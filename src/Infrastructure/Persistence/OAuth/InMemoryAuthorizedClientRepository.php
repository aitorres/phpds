<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizedClient;
use App\Domain\OAuth\AuthorizedClientNotFoundException;
use App\Domain\OAuth\AuthorizedClientRepository;

class InMemoryAuthorizedClientRepository implements AuthorizedClientRepository
{
    /**
     * Flat list; composite key (did, clientId).
     *
     * @var AuthorizedClient[]
     */
    private array $entries = [];

    /**
     * @param AuthorizedClient[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        $this->entries = $seeds;
    }

    public function findByDidAndClientId(string $did, string $clientId): AuthorizedClient
    {
        foreach ($this->entries as $entry) {
            if ($entry->getDid() === $did && $entry->getClientId() === $clientId) {
                return $entry;
            }
        }

        throw new AuthorizedClientNotFoundException();
    }

    /**
     * @return AuthorizedClient[]
     */
    public function findAllForDid(string $did): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(AuthorizedClient $e) => $e->getDid() === $did,
            )
        );
    }

    public function save(AuthorizedClient $authorizedClient): void
    {
        foreach ($this->entries as $i => $existing) {
            if (
                $existing->getDid() === $authorizedClient->getDid()
                && $existing->getClientId() === $authorizedClient->getClientId()
            ) {
                $this->entries[$i] = $authorizedClient;
                return;
            }
        }
        $this->entries[] = $authorizedClient;
    }

    public function deleteByDidAndClientId(string $did, string $clientId): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(AuthorizedClient $e) => !($e->getDid() === $did && $e->getClientId() === $clientId),
            )
        );
    }

    public function deleteAllForDid(string $did): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(AuthorizedClient $e) => $e->getDid() !== $did,
            )
        );
    }
}
