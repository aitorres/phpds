<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\OAuthToken;
use App\Domain\OAuth\OAuthTokenNotFoundException;
use App\Domain\OAuth\OAuthTokenRepository;

class InMemoryOAuthTokenRepository implements OAuthTokenRepository
{
    /** @var array<string, OAuthToken> keyed by tokenId */
    private array $tokens = [];

    /**
     * @param OAuthToken[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $token) {
            $this->tokens[$token->getTokenId()] = $token;
        }
    }

    public function findByTokenId(string $tokenId): OAuthToken
    {
        if (!isset($this->tokens[$tokenId])) {
            throw new OAuthTokenNotFoundException();
        }

        return $this->tokens[$tokenId];
    }

    public function findByCode(string $code): OAuthToken
    {
        foreach ($this->tokens as $token) {
            if ($token->getCode() === $code) {
                return $token;
            }
        }

        throw new OAuthTokenNotFoundException();
    }

    public function findByRefreshToken(string $refreshToken): OAuthToken
    {
        foreach ($this->tokens as $token) {
            if ($token->getCurrentRefreshToken() === $refreshToken) {
                return $token;
            }
        }

        throw new OAuthTokenNotFoundException();
    }

    /**
     * @return OAuthToken[]
     */
    public function findAllForDid(string $did): array
    {
        return array_values(
            array_filter(
                $this->tokens,
                fn(OAuthToken $t) => $t->getDid() === $did,
            )
        );
    }

    public function save(OAuthToken $token): void
    {
        $this->tokens[$token->getTokenId()] = $token;
    }

    public function deleteByTokenId(string $tokenId): void
    {
        unset($this->tokens[$tokenId]);
    }

    public function deleteAllForDid(string $did): void
    {
        $this->tokens = array_filter(
            $this->tokens,
            fn(OAuthToken $t) => $t->getDid() !== $did,
        );
    }
}
