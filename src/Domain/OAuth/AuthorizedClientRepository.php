<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface AuthorizedClientRepository
{
    /**
     * @throws AuthorizedClientNotFoundException
     */
    public function findByDidAndClientId(string $did, string $clientId): AuthorizedClient;

    /**
     * @return AuthorizedClient[]
     */
    public function findAllForDid(string $did): array;

    public function save(AuthorizedClient $authorizedClient): void;

    public function deleteByDidAndClientId(string $did, string $clientId): void;

    public function deleteAllForDid(string $did): void;
}
