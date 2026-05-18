<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface OAuthTokenRepository
{
    /**
     * @throws OAuthTokenNotFoundException
     */
    public function findByTokenId(string $tokenId): OAuthToken;

    /**
     * @throws OAuthTokenNotFoundException
     */
    public function findByCode(string $code): OAuthToken;

    /**
     * @throws OAuthTokenNotFoundException
     */
    public function findByRefreshToken(string $refreshToken): OAuthToken;

    /**
     * @return OAuthToken[]
     */
    public function findAllForDid(string $did): array;

    public function save(OAuthToken $token): void;

    public function deleteByTokenId(string $tokenId): void;

    public function deleteAllForDid(string $did): void;
}
