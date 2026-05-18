<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface UsedRefreshTokenRepository
{
    public function exists(string $refreshToken): bool;

    public function save(UsedRefreshToken $usedRefreshToken): void;

    public function deleteAllForTokenId(int $tokenId): void;
}
