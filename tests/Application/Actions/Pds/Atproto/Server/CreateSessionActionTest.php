<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\Atproto\Server\CreateSessionAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Account\Account;
use App\Domain\Account\AccountTakedownException;
use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\Auth\AccountAuthenticator;
use App\Domain\Account\Auth\AuthenticatedAccount;
use App\Domain\Account\Auth\InvalidCredentialsException;
use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Actor\Actor;
use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Auth\AuthTokenPair;
use App\Domain\Did\DidResolver;
use DateTimeImmutable;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class CreateSessionActionTest extends TestCase
{
    /** @var ObjectProphecy<AccountAuthenticator> */
    private ObjectProphecy $authenticator;
    /** @var ObjectProphecy<RefreshTokenRepository> */
    private ObjectProphecy $refreshTokens;
    /** @var ObjectProphecy<AuthTokenIssuer> */
    private ObjectProphecy $issuer;
    /** @var ObjectProphecy<DidResolver> */
    private ObjectProphecy $didResolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticator = $this->prophesize(AccountAuthenticator::class);
        $this->refreshTokens = $this->prophesize(RefreshTokenRepository::class);
        $this->issuer        = $this->prophesize(AuthTokenIssuer::class);
        $this->didResolver   = $this->prophesize(DidResolver::class);
        $this->didResolver->resolve(Argument::any())->willReturn(null);
    }

    private function makeAction(): CreateSessionAction
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        return new CreateSessionAction(
            $logger,
            $settings,
            $this->authenticator->reveal(),
            $this->refreshTokens->reveal(),
            $this->issuer->reveal(),
            $this->didResolver->reveal(),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function invoke(array $body): ResponseInterface
    {
        $request = $this->createRequest('POST', '/xrpc/com.atproto.server.createSession')
            ->withParsedBody($body);
        $response = (new ResponseFactory())->createResponse();
        return ($this->makeAction())($request, $response, []);
    }

    private function tokenPair(): AuthTokenPair
    {
        return new AuthTokenPair(
            accessJwt: 'access.jwt.value',
            refreshJwt: 'refresh.jwt.value',
            refreshJti: 'jti-abc',
            refreshExpiresAt: new DateTimeImmutable('2026-04-01T00:00:00Z'),
        );
    }

    private function makeAccount(string $did = 'did:plc:alice', string $email = 'alice@example.com'): Account
    {
        return new Account(
            did: $did,
            email: $email,
            passwordScrypt: 'stored-scrypt-hash',
            emailConfirmedAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    private function makeActor(?string $handle = 'alice.pds.test', ?DateTimeImmutable $deactivatedAt = null): Actor
    {
        return new Actor(
            did: 'did:plc:alice',
            handle: $handle,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            deactivatedAt: $deactivatedAt,
        );
    }

    public function testSuccessfulMainPasswordLoginReturnsSessionPayload(): void
    {
        $authenticated = new AuthenticatedAccount($this->makeAccount(), $this->makeActor(), null);
        $this->authenticator->login('alice.pds.test', 'hunter2')->willReturn($authenticated)->shouldBeCalledOnce();
        $this->issuer
            ->issue('did:plc:alice', AuthTokenIssuer::SCOPE_ACCESS, null)
            ->willReturn($this->tokenPair())
            ->shouldBeCalledOnce();
        $this->refreshTokens->save(Argument::that(function (RefreshToken $rt): bool {
            return $rt->getId() === 'jti-abc'
                && $rt->getDid() === 'did:plc:alice'
                && $rt->getAppPasswordName() === null
                && $rt->getNextId() === null
                && str_starts_with($rt->getExpiresAt(), '2026-04-01');
        }))->shouldBeCalledOnce();

        $response = $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'hunter2']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('access.jwt.value', $payload['accessJwt']);
        $this->assertSame('refresh.jwt.value', $payload['refreshJwt']);
        $this->assertSame('did:plc:alice', $payload['did']);
        $this->assertSame('alice.pds.test', $payload['handle']);
        $this->assertSame('alice@example.com', $payload['email']);
        $this->assertTrue($payload['emailConfirmed']);
        $this->assertTrue($payload['active']);
        $this->assertArrayNotHasKey('status', $payload);
        $this->assertArrayNotHasKey('didDoc', $payload);
    }

    public function testDidDocIsIncludedWhenResolved(): void
    {
        $didDoc = ['id' => 'did:plc:alice', '@context' => ['https://www.w3.org/ns/did/v1']];
        $authenticated = new AuthenticatedAccount($this->makeAccount(), $this->makeActor(), null);
        $this->authenticator->login(Argument::cetera())->willReturn($authenticated);
        $this->issuer->issue(Argument::cetera())->willReturn($this->tokenPair());
        $this->refreshTokens->save(Argument::any())->shouldBeCalledOnce();
        $this->didResolver->resolve('did:plc:alice')->willReturn($didDoc)->shouldBeCalledOnce();

        $response = $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'hunter2']);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame($didDoc, $payload['didDoc']);
    }

    public function testAppPasswordLoginIssuesAppPassScopeAndPersistsName(): void
    {
        $appPassword = new AppPassword(
            did: 'did:plc:alice',
            name: 'phone',
            passwordScrypt: 'app-scrypt-hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            privileged: false,
        );
        $authenticated = new AuthenticatedAccount($this->makeAccount(), $this->makeActor(), $appPassword);
        $this->authenticator->login('alice.pds.test', 'app-pass-123')->willReturn($authenticated);
        $this->issuer
            ->issue('did:plc:alice', AuthTokenIssuer::SCOPE_APP_PASS, 'phone')
            ->willReturn($this->tokenPair())
            ->shouldBeCalledOnce();
        $this->refreshTokens->save(Argument::that(function (RefreshToken $rt): bool {
            return $rt->getAppPasswordName() === 'phone';
        }))->shouldBeCalledOnce();

        $response = $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'app-pass-123']);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPrivilegedAppPasswordIssuesPrivilegedScope(): void
    {
        $appPassword = new AppPassword(
            did: 'did:plc:alice',
            name: 'phone',
            passwordScrypt: 'app-scrypt-hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            privileged: true,
        );
        $authenticated = new AuthenticatedAccount($this->makeAccount(), $this->makeActor(), $appPassword);
        $this->authenticator->login(Argument::cetera())->willReturn($authenticated);
        $this->issuer
            ->issue('did:plc:alice', AuthTokenIssuer::SCOPE_APP_PASS_PRIVILEGED, 'phone')
            ->willReturn($this->tokenPair())
            ->shouldBeCalledOnce();
        $this->refreshTokens->save(Argument::any())->shouldBeCalledOnce();

        $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'app-pass-123']);
    }

    public function testInvalidCredentialsAreMappedToAuthRequired(): void
    {
        $this->authenticator->login('ghost.pds.test', 'hunter2')->willThrow(new InvalidCredentialsException());
        $this->refreshTokens->save(Argument::any())->shouldNotBeCalled();

        try {
            $this->invoke(['identifier' => 'ghost.pds.test', 'password' => 'hunter2']);
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame('AuthenticationRequired', $e->getError());
            $this->assertSame(401, $e->getStatusCode());
            $this->assertSame('Invalid identifier or password', $e->getMessage());
        }
    }

    public function testTakedownIsMappedToAccountTakedownByDefault(): void
    {
        $this->authenticator->login(Argument::cetera())->willThrow(new AccountTakedownException());
        $this->issuer->issue(Argument::cetera())->shouldNotBeCalled();
        $this->refreshTokens->save(Argument::any())->shouldNotBeCalled();

        try {
            $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'hunter2']);
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame('AccountTakedown', $e->getError());
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function testAllowTakendownPropagatesTakedownException(): void
    {
        $this->authenticator->login(Argument::cetera())->willThrow(new AccountTakedownException());

        $this->expectException(AccountTakedownException::class);
        $this->invoke([
            'identifier' => 'alice.pds.test',
            'password' => 'hunter2',
            'allowTakendown' => true,
        ]);
    }

    public function testMissingIdentifierIsRejected(): void
    {
        $this->authenticator->login(Argument::cetera())->shouldNotBeCalled();
        $this->refreshTokens->save(Argument::any())->shouldNotBeCalled();

        try {
            $this->invoke(['password' => 'hunter2']);
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertStringContainsString('identifier', $e->getMessage());
        }
    }

    public function testMissingPasswordIsRejected(): void
    {
        $this->authenticator->login(Argument::cetera())->shouldNotBeCalled();
        $this->refreshTokens->save(Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('password');
        $this->invoke(['identifier' => 'alice.pds.test']);
    }

    public function testNonObjectBodyIsRejected(): void
    {
        $request = $this->createRequest('POST', '/xrpc/com.atproto.server.createSession');
        $response = (new ResponseFactory())->createResponse();

        $this->expectException(XrpcException::class);
        ($this->makeAction())($request, $response, []);
    }

    public function testOverlongPasswordIsRejectedWithAuthRequired(): void
    {
        $this->authenticator->login(Argument::cetera())->shouldNotBeCalled();
        $this->refreshTokens->save(Argument::any())->shouldNotBeCalled();

        try {
            $this->invoke([
                'identifier' => 'alice.pds.test',
                'password'   => str_repeat('a', CreateSessionAction::MAX_PASSWORD_LENGTH + 1),
            ]);
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame('AuthenticationRequired', $e->getError());
            $this->assertStringContainsString('Password too long', $e->getMessage());
        }
    }

    public function testDeactivatedAccountReturnsDeactivatedStatus(): void
    {
        $authenticated = new AuthenticatedAccount(
            $this->makeAccount(),
            $this->makeActor('alice.pds.test', new DateTimeImmutable('2026-02-01T00:00:00Z')),
            null,
        );
        $this->authenticator->login(Argument::cetera())->willReturn($authenticated);
        $this->issuer->issue(Argument::cetera())->willReturn($this->tokenPair());
        $this->refreshTokens->save(Argument::any())->shouldBeCalledOnce();

        $response = $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'hunter2']);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertFalse($payload['active']);
        $this->assertSame('deactivated', $payload['status']);
    }

    public function testHandleFallsBackToHandleInvalidWhenActorHandleMissing(): void
    {
        $authenticated = new AuthenticatedAccount($this->makeAccount(), $this->makeActor(null), null);
        $this->authenticator->login(Argument::cetera())->willReturn($authenticated);
        $this->issuer->issue(Argument::cetera())->willReturn($this->tokenPair());
        $this->refreshTokens->save(Argument::any())->shouldBeCalledOnce();

        $response = $this->invoke(['identifier' => 'alice.pds.test', 'password' => 'hunter2']);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame('handle.invalid', $payload['handle']);
    }
}
