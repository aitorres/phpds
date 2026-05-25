<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Account\AccountCreator;
use App\Domain\Account\Exception\AccountAlreadyExistsException;
use App\Domain\Account\Exception\EmailAlreadyTakenException;
use App\Domain\Account\Exception\HandleNotAvailableException;
use App\Domain\Account\Exception\InvalidEmailException;
use App\Domain\Account\Exception\InvalidHandleException;
use App\Domain\Account\Exception\InvalidInviteCodeException;
use App\Domain\Account\Exception\UnsupportedDomainException;
use App\Domain\Pds\Atproto\Server\CreateAccountResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

/**
 * `com.atproto.server.createAccount`
 *
 * Creates a new account on this PDS. Accepts `{handle, email, password,
 * inviteCode?, did?, plcOp?, recoveryKey?}`; mints a fresh `did:plc:*`
 * when no DID is supplied; otherwise accepts a caller-supplied
 * did:plc/did:web (with optional plcOp).
 *
 * @see https://docs.bsky.app/docs/api/com-atproto-server-create-account
 */
class CreateAccountAction extends PdsAction
{
    public const MAX_PASSWORD_LENGTH = 256;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        private readonly AccountCreator $accountCreator,
    ) {
        parent::__construct($logger, $settings, 'com.atproto.server.createAccount');
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

        $handle      = $this->requireString($body, 'handle');
        $email       = $this->requireString($body, 'email');
        $password    = $this->requireString($body, 'password');
        $inviteCode  = $this->optionalString($body, 'inviteCode');
        $did         = $this->optionalString($body, 'did');
        $recoveryKey = $this->optionalString($body, 'recoveryKey');

        if (strlen($password) > self::MAX_PASSWORD_LENGTH) {
            throw XrpcException::invalidParam($this->actionName, 'password too long', '');
        }

        $plcOp = $body['plcOp'] ?? null;
        if ($plcOp !== null && !is_array($plcOp)) {
            throw XrpcException::invalidParam($this->actionName, 'plcOp must be an object', '');
        }
        /** @var array<string, mixed>|null $plcOp */

        try {
            ['result' => $result, 'tokens' => $tokens] = $this->accountCreator->create(
                handle: $handle,
                email: $email,
                password: $password,
                inviteCode: $inviteCode,
                providedDid: $did,
                providedPlcOp: $plcOp,
                recoveryKey: $recoveryKey,
            );
        } catch (InvalidEmailException $e) {
            throw new XrpcException('InvalidEmail', $e->getMessage(), 400);
        } catch (InvalidHandleException $e) {
            throw new XrpcException('InvalidHandle', $e->getMessage(), 400);
        } catch (UnsupportedDomainException $e) {
            throw new XrpcException('UnsupportedDomain', $e->getMessage(), 400);
        } catch (InvalidInviteCodeException $e) {
            throw new XrpcException('InvalidInviteCode', $e->getMessage(), 400);
        } catch (EmailAlreadyTakenException $e) {
            throw new XrpcException('EmailAlreadyTaken', $e->getMessage(), 400);
        } catch (HandleNotAvailableException $e) {
            throw new XrpcException('HandleNotAvailable', $e->getMessage(), 400);
        } catch (AccountAlreadyExistsException $e) {
            throw new XrpcException('AccountAlreadyExists', $e->getMessage(), 400);
        } catch (\InvalidArgumentException $e) {
            throw XrpcException::invalidRequest($e->getMessage());
        }

        return $this->respondWithData(new CreateAccountResponse(
            accessJwt: $tokens->getAccessJwt(),
            refreshJwt: $tokens->getRefreshJwt(),
            handle: $result->getHandle(),
            did: $result->getDid(),
            didDoc: $result->getDidDoc(),
        ));
    }
}
