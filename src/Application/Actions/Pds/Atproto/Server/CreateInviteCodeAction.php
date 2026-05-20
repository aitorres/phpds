<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeGenerator;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Pds\Atproto\Server\CreateInviteCodeResponse;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

/**
 * `com.atproto.server.createInviteCode`
 *
 * Creates a single invite code with the given `useCount`, optionally
 * scoped to a specific account DID (`forAccount`, defaults to "admin").
 *
 * @see https://docs.bsky.app/docs/api/com-atproto-server-create-invite-code
 */
class CreateInviteCodeAction extends PdsAction
{
    public const DEFAULT_FOR_ACCOUNT = 'admin';

    private InviteCodeRepository $inviteCodes;

    private InviteCodeGenerator $generator;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        InviteCodeRepository $inviteCodes,
        InviteCodeGenerator $generator
    ) {
        parent::__construct($logger, $settings, 'com.atproto.server.createInviteCode');
        $this->inviteCodes = $inviteCodes;
        $this->generator = $generator;
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

        $useCount = $this->parseUseCount($body['useCount'] ?? null);
        $forAccount = $this->parseForAccount($body['forAccount'] ?? null);

        $code = new InviteCode(
            code: $this->generator->generate(),
            availableUses: $useCount,
            disabled: false,
            forAccount: $forAccount,
            createdBy: 'admin',
            createdAt: new DateTimeImmutable(),
        );

        $this->inviteCodes->save($code);

        return $this->respondWithData(new CreateInviteCodeResponse($code->getCode()));
    }

    private function parseUseCount(mixed $raw): int
    {
        if ($raw === null) {
            $this->throwMissingKeyException('useCount');
        }

        if (is_int($raw)) {
            $value = $raw;
        } elseif (is_string($raw) && preg_match('/^-?\d+$/', $raw)) {
            $value = (int) $raw;
        } else {
            throw XrpcException::invalidParam(
                $this->actionName,
                'useCount must be an integer',
                is_scalar($raw) ? (string) $raw : ''
            );
        }

        if ($value < 1) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'useCount must be a positive integer',
                (string) $value
            );
        }

        return $value;
    }

    private function parseForAccount(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_FOR_ACCOUNT;
        }

        if (!is_string($raw)) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'forAccount must be a string',
                is_scalar($raw) ? (string) $raw : ''
            );
        }

        return $raw;
    }
}
