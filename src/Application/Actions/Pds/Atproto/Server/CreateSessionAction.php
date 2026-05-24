<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountTakedownException;
use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\Auth\AccountAuthenticator;
use App\Domain\Account\Auth\AuthenticatedAccount;
use App\Domain\Account\Auth\InvalidCredentialsException;
use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;
use App\Domain\Auth\AuthTokenIssuer;
use App\Domain\Did\DidResolver;
use App\Domain\Pds\Atproto\Server\CreateSessionResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

/**
 * `com.atproto.server.createSession`
 *
 * Authenticates a client using `{identifier, password}` and returns a fresh
 * access/refresh JWT pair along with the resolved account metadata. The
 * identifier may be the account's DID, email, or handle. The password may
 * be the main account password or any of the account's app passwords; when
 * an app password is used, the issued access JWT carries an `appPass` scope.
 *
 * @see https://docs.bsky.app/docs/api/com-atproto-server-create-session
 */
class CreateSessionAction extends PdsAction
{
    public const MAX_PASSWORD_LENGTH = 256;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        private readonly AccountAuthenticator $authenticator,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly AuthTokenIssuer $tokenIssuer,
        private readonly DidResolver $didResolver,
    ) {
        parent::__construct($logger, $settings, 'com.atproto.server.createSession');
    }

    /**
     * {@inheritdoc}
     */
    protected function action(): Response
    {
        $body = $this->getFormData();
        if (!is_array($body)) {
            throw XrpcException::invalidRequest('Request body must be a JSON object');
        }

        $identifier = $this->requireString($body, 'identifier');
        $password   = $this->requireString($body, 'password');
        $allowTakendown = $this->parseAllowTakendown($body['allowTakendown'] ?? null);

        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw XrpcException::authRequired(
                'Password too long. Consider resetting your password.'
            );
        }

        $authenticated = $this->authenticate($identifier, $password, $allowTakendown);

        $tokens = $this->tokenIssuer->issue(
            $authenticated->account->getDid(),
            $this->scopeFor($authenticated->appPassword),
            $authenticated->appPassword?->getName()
        );

        $this->refreshTokens->save(new RefreshToken(
            id: $tokens->getRefreshJti(),
            did: $authenticated->account->getDid(),
            expiresAt: $tokens->getRefreshExpiresAt()->format(DATE_ATOM),
            appPasswordName: $authenticated->appPassword?->getName(),
            nextId: null,
        ));

        $actor = $authenticated->actor;
        $didDoc = $this->didResolver->resolve($authenticated->account->getDid());
        $response = new CreateSessionResponse(
            accessJwt: $tokens->getAccessJwt(),
            refreshJwt: $tokens->getRefreshJwt(),
            did: $authenticated->account->getDid(),
            didDoc: $didDoc,
            handle: $actor->getHandle() ?? CreateSessionResponse::HANDLE_INVALID,
            email: $authenticated->account->getEmail(),
            emailConfirmed: $authenticated->account->getEmailConfirmedAt() !== null,
            active: $actor->isRepoActive(),
            status: $actor->getRepoStatus(),
        );

        return $this->respondWithData($response);
    }

    private function authenticate(string $identifier, string $password, bool $allowTakendown): AuthenticatedAccount
    {
        try {
            return $this->authenticator->login($identifier, $password);
        } catch (InvalidCredentialsException) {
            throw XrpcException::authRequired('Invalid identifier or password');
        } catch (AccountTakedownException $e) {
            if (!$allowTakendown) {
                throw new XrpcException('AccountTakedown', $e->getMessage(), 401);
            }
            throw $e;
        }
    }

    private function scopeFor(?AppPassword $appPassword): string
    {
        if ($appPassword === null) {
            return AuthTokenIssuer::SCOPE_ACCESS;
        }

        return $appPassword->isPrivileged()
            ? AuthTokenIssuer::SCOPE_APP_PASS_PRIVILEGED
            : AuthTokenIssuer::SCOPE_APP_PASS;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function requireString(array $body, string $key): string
    {
        $value = $body[$key] ?? null;
        if (!is_string($value) || $value === '') {
            $this->throwMissingKeyException($key);
        }
        return $value;
    }

    private function parseAllowTakendown(mixed $raw): bool
    {
        if ($raw === null) {
            return false;
        }
        if (is_bool($raw)) {
            return $raw;
        }
        if ($raw === 'true' || $raw === 1 || $raw === '1') {
            return true;
        }
        if ($raw === 'false' || $raw === 0 || $raw === '0') {
            return false;
        }
        throw XrpcException::invalidParam(
            $this->actionName,
            'allowTakendown must be a boolean',
            is_scalar($raw) ? (string) $raw : ''
        );
    }
}
