<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\UsedRefreshToken;
use App\Domain\OAuth\UsedRefreshTokenRepository;

class InMemoryUsedRefreshTokenRepository implements UsedRefreshTokenRepository
{
    /** @var array<string, UsedRefreshToken> keyed by refreshToken string */
    private array $tokens = [];

    /**
     * @param UsedRefreshToken[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $token) {
            $this->tokens[$token->getRefreshToken()] = $token;
        }
    }

    public function exists(string $refreshToken): bool
    {
        return isset($this->tokens[$refreshToken]);
    }

    public function save(UsedRefreshToken $usedRefreshToken): void
    {
        $this->tokens[$usedRefreshToken->getRefreshToken()] = $usedRefreshToken;
    }

    public function deleteAllForTokenId(int $tokenId): void
    {
        $this->tokens = array_filter(
            $this->tokens,
            fn(UsedRefreshToken $t) => $t->getTokenId() !== $tokenId,
        );
    }
}
