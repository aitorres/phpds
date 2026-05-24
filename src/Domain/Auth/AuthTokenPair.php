<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

/**
 * A freshly minted (access, refresh) JWT pair issued by {@see AuthTokenIssuer}.
 *
 * The refresh token's identifier (`jti`) and absolute expiry are exposed so
 * the caller can persist the refresh token.
 */
final class AuthTokenPair
{
    public function __construct(
        private readonly string $accessJwt,
        private readonly string $refreshJwt,
        private readonly string $refreshJti,
        private readonly DateTimeImmutable $refreshExpiresAt,
    ) {
    }

    public function getAccessJwt(): string
    {
        return $this->accessJwt;
    }

    public function getRefreshJwt(): string
    {
        return $this->refreshJwt;
    }

    public function getRefreshJti(): string
    {
        return $this->refreshJti;
    }

    public function getRefreshExpiresAt(): DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }
}
