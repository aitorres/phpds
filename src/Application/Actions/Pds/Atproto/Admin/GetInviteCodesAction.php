<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds\Atproto\Admin;

use App\Application\Actions\Pds\PdsAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Domain\Pds\Atproto\Admin\GetInviteCodesResponse;
use App\Domain\Pds\Atproto\Admin\InviteCodeUseView;
use App\Domain\Pds\Atproto\Admin\InviteCodeView;
use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

class GetInviteCodesAction extends PdsAction
{
    public const SORT_RECENT = 'recent';

    public const SORT_USAGE = 'usage';

    public const DEFAULT_LIMIT = 100;

    public const MAX_LIMIT = 500;

    public function __construct(
        LoggerInterface $logger,
        SettingsInterface $settings,
        private readonly InviteCodeRepository $inviteCodes,
    ) {
        parent::__construct($logger, $settings, 'com.atproto.admin.getInviteCodes');
    }

    protected function action(): Response
    {
        $params = $this->request->getQueryParams();

        $sort = $this->parseSort($params['sort'] ?? null);
        $limit = $this->parseLimit($params['limit'] ?? null);

        if ($sort === self::SORT_USAGE) {
            [$cursorUses, $cursorCode] = $this->parseUsageCursor($params['cursor'] ?? null);
            $codes = $this->inviteCodes->findPageByUsage($cursorUses, $cursorCode, $limit);
        } else {
            [$cursorCreatedAt, $cursorCode] = $this->parseRecentCursor($params['cursor'] ?? null);
            $codes = $this->inviteCodes->findPageByRecent($cursorCreatedAt, $cursorCode, $limit);
        }

        $codeIds = array_values(array_map(
            static fn (InviteCode $code): string => $code->getCode(),
            $codes
        ));
        $usesByCode = $this->inviteCodes->findUsesForCodes($codeIds);

        $views = array_values(array_map(
            function (InviteCode $code) use ($usesByCode): InviteCodeView {
                $uses = array_map(
                    static fn (InviteCodeUse $use): InviteCodeUseView => new InviteCodeUseView(
                        usedBy: $use->getUsedBy(),
                        usedAt: $use->getUsedAt()->format(DATE_ATOM),
                    ),
                    $usesByCode[$code->getCode()] ?? []
                );

                return new InviteCodeView(
                    code: $code->getCode(),
                    available: $code->getAvailableUses(),
                    disabled: $code->isDisabled(),
                    forAccount: $code->getForAccount(),
                    createdBy: $code->getCreatedBy(),
                    createdAt: $code->getCreatedAt()->format(DATE_ATOM),
                    uses: $uses,
                );
            },
            $codes
        ));

        $nextCursor = null;
        $lastCode = end($codes);
        if ($lastCode instanceof InviteCode) {
            $uses = $usesByCode[$lastCode->getCode()] ?? [];
            $nextCursor = $sort === self::SORT_USAGE
                ? $this->packUsageCursor(count($uses), $lastCode->getCode())
                : $this->packRecentCursor($lastCode->getCreatedAt(), $lastCode->getCode());
        }

        return $this->respondWithData(new GetInviteCodesResponse($views, $nextCursor));
    }

    private function parseSort(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return self::SORT_RECENT;
        }

        if (!is_string($raw)) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'sort must be a string',
                is_scalar($raw) ? (string) $raw : ''
            );
        }

        if ($raw !== self::SORT_RECENT && $raw !== self::SORT_USAGE) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'sort must be one of "recent" or "usage"',
                $raw
            );
        }

        return $raw;
    }

    private function parseLimit(mixed $raw): int
    {
        if ($raw === null || $raw === '') {
            return self::DEFAULT_LIMIT;
        }

        if (!is_string($raw) && !is_int($raw)) {
            throw XrpcException::invalidParam(
                $this->actionName,
                'limit must be an integer',
                is_scalar($raw) ? (string) $raw : ''
            );
        }

        if (is_string($raw) && !preg_match('/^-?\d+$/', $raw)) {
            throw XrpcException::invalidParam($this->actionName, 'limit must be an integer', $raw);
        }

        $limit = (int) $raw;
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw XrpcException::invalidParam(
                $this->actionName,
                sprintf('limit must be between 1 and %d', self::MAX_LIMIT),
                (string) $raw
            );
        }

        return $limit;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseRecentCursor(mixed $raw): array
    {
        $parts = $this->parseCursorParts($raw);
        if ($parts === null) {
            return [null, null];
        }

        [$primary, $secondary] = $parts;
        if (!preg_match('/^-?\d+$/', $primary)) {
            throw XrpcException::invalidRequest('Malformed cursor');
        }

        $seconds = intdiv((int) $primary, 1000);
        $createdAt = (new DateTimeImmutable('@' . (string) $seconds))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format(DATE_ATOM);

        return [$createdAt, $secondary];
    }

    /**
     * @return array{0: ?int, 1: ?string}
     */
    private function parseUsageCursor(mixed $raw): array
    {
        $parts = $this->parseCursorParts($raw);
        if ($parts === null) {
            return [null, null];
        }

        [$primary, $secondary] = $parts;
        if (!preg_match('/^-?\d+$/', $primary)) {
            throw XrpcException::invalidRequest('Malformed cursor');
        }

        return [(int) $primary, $secondary];
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseCursorParts(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_string($raw)) {
            throw XrpcException::invalidParam($this->actionName, 'cursor must be a string', '');
        }

        $cursor = trim($raw);
        $parts = explode('::', $cursor, 3);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw XrpcException::invalidRequest('Malformed cursor');
        }

        return [$parts[0], $parts[1]];
    }

    private function packRecentCursor(DateTimeImmutable $createdAt, string $code): string
    {
        return sprintf('%d::%s', $createdAt->getTimestamp() * 1000, $code);
    }

    private function packUsageCursor(int $uses, string $code): string
    {
        return sprintf('%d::%s', $uses, $code);
    }
}
