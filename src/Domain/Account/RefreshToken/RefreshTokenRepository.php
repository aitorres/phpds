<?php

declare(strict_types=1);

namespace App\Domain\Account\RefreshToken;

interface RefreshTokenRepository
{
    /**
     * @throws RefreshTokenNotFoundException
     */
    public function findById(string $id): RefreshToken;

    /**
     * @return RefreshToken[]
     */
    public function findAllForDid(string $did): array;

    public function save(RefreshToken $token): void;

    public function deleteById(string $id): void;

    public function deleteAllForDid(string $did): void;
}
