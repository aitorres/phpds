<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Admin;

use App\Application\Actions\Pds\Atproto\Admin\GetInviteCodesAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;
use DateTimeImmutable;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class GetInviteCodesActionTest extends TestCase
{
    private function makeAction(InviteCodeRepository $repo): GetInviteCodesAction
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        return new GetInviteCodesAction($logger, $settings, $repo);
    }

    private function makeCode(
        string $code,
        string $createdAt,
        int $availableUses = 1,
        bool $disabled = false,
        string $forAccount = 'did:plc:alice',
        string $createdBy = 'admin',
    ): InviteCode {
        return new InviteCode(
            code: $code,
            availableUses: $availableUses,
            disabled: $disabled,
            forAccount: $forAccount,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable($createdAt),
        );
    }

    private function makeUse(string $code, string $usedBy, string $usedAt): InviteCodeUse
    {
        return new InviteCodeUse($code, $usedBy, new DateTimeImmutable($usedAt));
    }

    /**
     * @param array<string, scalar> $query
     */
    private function invoke(GetInviteCodesAction $action, array $query = []): ResponseInterface
    {
        $request = $this->createRequest('GET', '/xrpc/com.atproto.admin.getInviteCodes')
            ->withQueryParams($query);
        $response = (new ResponseFactory())->createResponse();

        return $action($request, $response, []);
    }

    public function testDefaultsToRecentSortAndIncludesInviteUses(): void
    {
        $newer = $this->makeCode('code-new', '2026-01-03T00:00:00Z', availableUses: 5, disabled: true);
        $older = $this->makeCode('code-old', '2026-01-02T00:00:00Z', availableUses: 3);

        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByRecent(null, null, GetInviteCodesAction::DEFAULT_LIMIT)
            ->willReturn([$newer, $older])
            ->shouldBeCalledOnce();
        $repo->findPageByUsage(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();
        $repo->findUsesForCodes(['code-new', 'code-old'])
            ->willReturn([
                'code-new' => [
                    $this->makeUse('code-new', 'did:plc:code-new-user', '2026-01-04T00:00:00Z'),
                ],
                'code-old' => [],
            ])
            ->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $codes = $payload['codes'] ?? null;
        $this->assertIsArray($codes);
        $first = $codes[0] ?? null;
        $second = $codes[1] ?? null;
        $this->assertIsArray($first);
        $this->assertIsArray($second);
        $firstUses = $first['uses'] ?? null;
        $secondUses = $second['uses'] ?? null;
        $this->assertIsArray($firstUses);
        $this->assertIsArray($secondUses);

        $this->assertSame('1767312000000::code-old', $payload['cursor']);
        $this->assertSame('code-new', $first['code']);
        $this->assertSame(5, $first['available']);
        $this->assertTrue($first['disabled']);
        $this->assertSame([
            'usedBy' => 'did:plc:code-new-user',
            'usedAt' => '2026-01-04T00:00:00+00:00',
        ], $firstUses[0] ?? null);
        $this->assertSame([], $secondUses);
    }

    public function testSupportsUsageSortAndUsageCursor(): void
    {
        $medium = $this->makeCode('code-medium', '2026-01-02T00:00:00Z');
        $least = $this->makeCode('code-least', '2026-01-01T00:00:00Z');

        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByUsage(null, null, 2)
            ->willReturn([$medium, $least])
            ->shouldBeCalledOnce();
        $repo->findPageByRecent(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();
        $repo->findUsesForCodes(['code-medium', 'code-least'])
            ->willReturn([
                'code-medium' => [
                    $this->makeUse('code-medium', 'did:plc:code-medium-user-2', '2026-01-04T00:00:00Z'),
                    $this->makeUse('code-medium', 'did:plc:code-medium-user-1', '2026-01-03T00:00:00Z'),
                ],
                'code-least' => [
                    $this->makeUse('code-least', 'did:plc:code-least-user-1', '2026-01-02T00:00:00Z'),
                ],
            ])
            ->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()), [
            'sort' => 'usage',
            'limit' => '2',
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $codes = $payload['codes'] ?? null;
        $this->assertIsArray($codes);
        $first = $codes[0] ?? null;
        $this->assertIsArray($first);
        $firstUses = $first['uses'] ?? null;
        $this->assertIsArray($firstUses);

        $this->assertSame('1::code-least', $payload['cursor']);
        $this->assertSame('code-medium', $first['code']);
        $this->assertCount(2, $firstUses);
    }

    public function testRecentCursorIsParsedForPagination(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByRecent('2026-01-02T00:00:00+00:00', 'code-new', GetInviteCodesAction::DEFAULT_LIMIT)
            ->willReturn([])
            ->shouldBeCalledOnce();
        $repo->findUsesForCodes([])->willReturn([])->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()), [
            'cursor' => '1767312000000::code-new',
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['codes' => []], $payload);
    }

    public function testUsageCursorIsParsedForPagination(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByUsage(3, 'code-high', GetInviteCodesAction::DEFAULT_LIMIT)
            ->willReturn([])
            ->shouldBeCalledOnce();
        $repo->findUsesForCodes([])->willReturn([])->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()), [
            'sort' => 'usage',
            'cursor' => '3::code-high',
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertSame(['codes' => []], $payload);
    }

    public function testRejectsUnknownSort(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByRecent(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();
        $repo->findPageByUsage(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->invoke($this->makeAction($repo->reveal()), ['sort' => 'alphabetical']);
    }

    public function testRejectsOutOfRangeLimit(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByRecent(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->invoke($this->makeAction($repo->reveal()), ['limit' => '501']);
    }

    public function testRejectsMalformedCursor(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->findPageByRecent(Argument::any(), Argument::any(), Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('Malformed cursor');
        $this->invoke($this->makeAction($repo->reveal()), ['cursor' => 'not-a-cursor']);
    }
}
