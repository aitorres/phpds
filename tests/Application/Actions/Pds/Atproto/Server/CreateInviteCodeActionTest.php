<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\Atproto\Server\CreateInviteCodeAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeGenerator;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class CreateInviteCodeActionTest extends TestCase
{
    private function makeAction(InviteCodeRepository $repo): CreateInviteCodeAction
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);
        $generator = new InviteCodeGenerator('pds.test');
        return new CreateInviteCodeAction($logger, $settings, $repo, $generator);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function invoke(CreateInviteCodeAction $action, array $body): ResponseInterface
    {
        $request = $this->createRequest('POST', '/xrpc/com.atproto.server.createInviteCode')
            ->withParsedBody($body);
        $response = (new ResponseFactory())->createResponse();
        return $action($request, $response, []);
    }

    public function testCreatesInviteCodeWithDefaultForAccount(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::that(function (InviteCode $code): bool {
            return preg_match('/^pds-test-[a-z2-7]{5}-[a-z2-7]{5}$/', $code->getCode()) === 1
                && $code->getAvailableUses() === 1
                && $code->getForAccount() === 'admin'
                && $code->getCreatedBy() === 'admin'
                && $code->isDisabled() === false;
        }))->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()), ['useCount' => 1]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        $this->assertIsArray($payload);
        $code = $payload['code'];
        $this->assertIsString($code);
        $this->assertMatchesRegularExpression('/^pds-test-[a-z2-7]{5}-[a-z2-7]{5}$/', $code);
    }

    public function testRespectsExplicitForAccount(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::that(function (InviteCode $code): bool {
            return $code->getForAccount() === 'did:plc:alice' && $code->getAvailableUses() === 3;
        }))->shouldBeCalledOnce();

        $response = $this->invoke($this->makeAction($repo->reveal()), [
            'useCount' => 3,
            'forAccount' => 'did:plc:alice',
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMissingUseCountReturnsInvalidRequest(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::any())->shouldNotBeCalled();

        try {
            $this->invoke($this->makeAction($repo->reveal()), []);
            $this->fail('Expected XrpcException');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertStringContainsString('useCount', $e->getMessage());
        }
    }

    public function testNonIntegerUseCountIsRejected(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->invoke($this->makeAction($repo->reveal()), ['useCount' => 'banana']);
    }

    public function testZeroUseCountIsRejected(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->invoke($this->makeAction($repo->reveal()), ['useCount' => 0]);
    }

    public function testNonStringForAccountIsRejected(): void
    {
        $repo = $this->prophesize(InviteCodeRepository::class);
        $repo->save(Argument::any())->shouldNotBeCalled();

        $this->expectException(XrpcException::class);
        $this->invoke($this->makeAction($repo->reveal()), ['useCount' => 1, 'forAccount' => 42]);
    }
}
