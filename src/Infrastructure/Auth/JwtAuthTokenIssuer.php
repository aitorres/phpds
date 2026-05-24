<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Auth\AuthTokenPair;
use Closure;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Random\Randomizer;

/**
 * {@see AuthTokenIssuer} implementation backed by firebase/php-jwt.
 *
 * Tokens are signed with HS256 over a server-side shared secret. Access
 * tokens are short-lived (2 hours), refresh tokens are long-lived (90 days).
 */
final class JwtAuthTokenIssuer implements AuthTokenIssuer
{
    public const ALG = 'HS256';

    // 2 hours
    public const DEFAULT_ACCESS_TTL_SECONDS  = 7200;

    // 90 days
    public const DEFAULT_REFRESH_TTL_SECONDS = 7776000;

    private Randomizer $randomizer;

    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param (callable(): DateTimeImmutable)|null $clock
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $accessTtlSeconds = self::DEFAULT_ACCESS_TTL_SECONDS,
        private readonly int $refreshTtlSeconds = self::DEFAULT_REFRESH_TTL_SECONDS,
        ?callable $clock = null,
        ?Randomizer $randomizer = null,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('JWT signing secret must not be empty.');
        }
        $this->randomizer = $randomizer ?? new Randomizer();
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): DateTimeImmutable => new DateTimeImmutable();
    }

    public function issue(string $did, string $accessScope, ?string $appPasswordName = null): AuthTokenPair
    {
        $now = ($this->clock)();
        $accessExp  = $now->modify('+' . $this->accessTtlSeconds . ' seconds');
        $refreshExp = $now->modify('+' . $this->refreshTtlSeconds . ' seconds');
        $jti = $this->generateJti();

        $accessJwt = JWT::encode(
            [
                'scope' => $accessScope,
                'sub'   => $did,
                'iss'   => $this->issuer,
                'aud'   => 'did:web:' . $this->issuer,
                'iat'   => $now->getTimestamp(),
                'exp'   => $accessExp->getTimestamp(),
            ],
            $this->secret,
            self::ALG
        );

        $refreshClaims = [
            'scope' => self::SCOPE_REFRESH,
            'sub'   => $did,
            'iss'   => $this->issuer,
            'aud'   => 'did:web:' . $this->issuer,
            'jti'   => $jti,
            'iat'   => $now->getTimestamp(),
            'exp'   => $refreshExp->getTimestamp(),
        ];
        if ($appPasswordName !== null) {
            $refreshClaims['app_password_name'] = $appPasswordName;
        }

        $refreshJwt = JWT::encode($refreshClaims, $this->secret, self::ALG);

        return new AuthTokenPair($accessJwt, $refreshJwt, $jti, $refreshExp);
    }

    private function generateJti(): string
    {
        return rtrim(strtr(base64_encode($this->randomizer->getBytes(16)), '+/', '-_'), '=');
    }
}
