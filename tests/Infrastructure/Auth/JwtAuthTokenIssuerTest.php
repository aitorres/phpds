<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Auth;

use App\Domain\Auth\AuthTokenIssuer;
use App\Infrastructure\Auth\JwtAuthTokenIssuer;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Tests\TestCase;

class JwtAuthTokenIssuerTest extends TestCase
{
    private const SECRET = 'test-secret-with-at-least-32-bytes-of-entropy';
    private const ISSUER = 'pds.test';

    protected function tearDown(): void
    {
        JWT::$timestamp = null;
        parent::tearDown();
    }

    public function testIssueProducesVerifiableAccessAndRefreshJwts(): void
    {
        $fixed = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $issuer = new JwtAuthTokenIssuer(
            secret: self::SECRET,
            issuer: self::ISSUER,
            accessTtlSeconds: 3600,
            refreshTtlSeconds: 86400,
            clock: static fn (): DateTimeImmutable => $fixed,
        );

        JWT::$timestamp = $fixed->getTimestamp() + 1;

        $pair = $issuer->issue('did:plc:alice', AuthTokenIssuer::SCOPE_ACCESS);

        $access = (array) JWT::decode($pair->getAccessJwt(), new Key(self::SECRET, 'HS256'));
        $this->assertSame('did:plc:alice', $access['sub']);
        $this->assertSame(AuthTokenIssuer::SCOPE_ACCESS, $access['scope']);
        $this->assertSame(self::ISSUER, $access['iss']);
        $this->assertSame('did:web:' . self::ISSUER, $access['aud']);
        $this->assertSame(1767225600, $access['iat']);
        $this->assertSame(1767225600 + 3600, $access['exp']);

        $refresh = (array) JWT::decode($pair->getRefreshJwt(), new Key(self::SECRET, 'HS256'));
        $this->assertSame('did:plc:alice', $refresh['sub']);
        $this->assertSame(AuthTokenIssuer::SCOPE_REFRESH, $refresh['scope']);
        $this->assertSame($pair->getRefreshJti(), $refresh['jti']);
        $this->assertSame(1767225600 + 86400, $refresh['exp']);
        $this->assertArrayNotHasKey('app_password_name', $refresh);

        $this->assertSame($pair->getRefreshExpiresAt()->getTimestamp(), 1767225600 + 86400);
    }

    public function testIssueEmbedsAppPasswordNameOnRefreshTokenWhenProvided(): void
    {
        $issuer = new JwtAuthTokenIssuer(self::SECRET, self::ISSUER);

        $pair = $issuer->issue('did:plc:alice', AuthTokenIssuer::SCOPE_APP_PASS, 'my-app');

        $refresh = (array) JWT::decode($pair->getRefreshJwt(), new Key(self::SECRET, 'HS256'));
        $this->assertSame('my-app', $refresh['app_password_name']);

        $access = (array) JWT::decode($pair->getAccessJwt(), new Key(self::SECRET, 'HS256'));
        $this->assertSame(AuthTokenIssuer::SCOPE_APP_PASS, $access['scope']);
    }

    public function testConsecutiveIssuesYieldDifferentJtis(): void
    {
        $issuer = new JwtAuthTokenIssuer(self::SECRET, self::ISSUER);

        $a = $issuer->issue('did:plc:alice', AuthTokenIssuer::SCOPE_ACCESS);
        $b = $issuer->issue('did:plc:alice', AuthTokenIssuer::SCOPE_ACCESS);

        $this->assertNotSame($a->getRefreshJti(), $b->getRefreshJti());
    }

    public function testEmptySecretIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JwtAuthTokenIssuer('', self::ISSUER);
    }
}
