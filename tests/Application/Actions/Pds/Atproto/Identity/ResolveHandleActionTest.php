<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Identity;

use App\Application\Actions\Pds\Atproto\Identity\ResolveHandleAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\Pds\Atproto\AppView\AppViewClient;
use App\Domain\Pds\Atproto\AppView\AppViewException;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class ResolveHandleActionTest extends TestCase
{
    public function testActionReturnsDidForKnownHandle(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = new Actor('did:web:alice.pds.test', 'alice.pds.test', new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $repositoryProphecy = $this->prophesize(ActorRepository::class);
        $repositoryProphecy
            ->findActorByHandle('alice.pds.test')
            ->willReturn($actor)
            ->shouldBeCalledOnce();
        $appView = $this->prophesize(AppViewClient::class)->reveal();

        $action = new ResolveHandleAction($logger, $settings, $repositoryProphecy->reveal(), $appView);

        $request = $this->createRequest(
            'GET',
            '/xrpc/com.atproto.identity.resolveHandle'
        )->withQueryParams(['handle' => 'alice.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expectedPayload = json_encode(['did' => 'did:web:alice.pds.test'], JSON_PRETTY_PRINT);

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame('application/json', $actualResponse->getHeaderLine('Content-Type'));
        $this->assertSame($expectedPayload, (string) $actualResponse->getBody());
    }

    public function testActionThrowsXrpcInvalidRequestWhenHandleParamMissing(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repository = $this->prophesize(ActorRepository::class)->reveal();
        $appView = $this->prophesize(AppViewClient::class)->reveal();

        $action = new ResolveHandleAction($logger, $settings, $repository, $appView);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.identity.resolveHandle');
        $response = (new ResponseFactory())->createResponse();

        try {
            $action($request, $response, []);
            $this->fail('Expected XrpcException was not thrown.');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame(
                'Invalid com.atproto.identity.resolveHandle params: Missing required key "handle"',
                $e->getMessage()
            );
        }
    }

    public function testActionThrowsXrpcInvalidRequestWhenHandleParamIsBlank(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repository = $this->prophesize(ActorRepository::class)->reveal();
        $appView = $this->prophesize(AppViewClient::class)->reveal();

        $action = new ResolveHandleAction($logger, $settings, $repository, $appView);

        $request = $this->createRequest(
            'GET',
            '/xrpc/com.atproto.identity.resolveHandle'
        )->withQueryParams(['handle' => '   ']);
        $response = (new ResponseFactory())->createResponse();

        $this->expectException(XrpcException::class);
        $action($request, $response, []);
    }

    public function testActionDefersToAppViewForExternalHandle(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        $repositoryProphecy = $this->prophesize(ActorRepository::class);
        $repositoryProphecy
            ->findActorByHandle('bob.bsky.social')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();

        $appViewProphecy = $this->prophesize(AppViewClient::class);
        $appViewProphecy
            ->resolveHandle('bob.bsky.social')
            ->willReturn('did:plc:bob000000000000000000000')
            ->shouldBeCalledOnce();

        $action = new ResolveHandleAction(
            $logger,
            $settings,
            $repositoryProphecy->reveal(),
            $appViewProphecy->reveal()
        );

        $request = $this->createRequest(
            'GET',
            '/xrpc/com.atproto.identity.resolveHandle'
        )->withQueryParams(['handle' => 'bob.bsky.social']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame(
            json_encode(['did' => 'did:plc:bob000000000000000000000'], JSON_PRETTY_PRINT),
            (string) $actualResponse->getBody()
        );
    }

    public function testActionThrowsXrpcInvalidRequestWhenAppViewFails(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        $repositoryProphecy = $this->prophesize(ActorRepository::class);
        $repositoryProphecy
            ->findActorByHandle('bob.bsky.social')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();

        $appViewProphecy = $this->prophesize(AppViewClient::class);
        $appViewProphecy
            ->resolveHandle('bob.bsky.social')
            ->willThrow(new AppViewException('boom'))
            ->shouldBeCalledOnce();

        $action = new ResolveHandleAction(
            $logger,
            $settings,
            $repositoryProphecy->reveal(),
            $appViewProphecy->reveal()
        );

        $request = $this->createRequest(
            'GET',
            '/xrpc/com.atproto.identity.resolveHandle'
        )->withQueryParams(['handle' => 'bob.bsky.social']);
        $response = (new ResponseFactory())->createResponse();

        try {
            $action($request, $response, []);
            $this->fail('Expected XrpcException was not thrown.');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Unable to resolve handle', $e->getMessage());
        }
    }

    public function testActionThrowsXrpcInvalidRequestWhenLocalHandleUnknown(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings(['pds' => ['hostname' => 'pds.test']]);

        $repositoryProphecy = $this->prophesize(ActorRepository::class);
        $repositoryProphecy
            ->findActorByHandle('missing.pds.test')
            ->willThrow(new ActorNotFoundException())
            ->shouldBeCalledOnce();
        $appView = $this->prophesize(AppViewClient::class)->reveal();

        $action = new ResolveHandleAction(
            $logger,
            $settings,
            $repositoryProphecy->reveal(),
            $appView
        );

        $request = $this->createRequest(
            'GET',
            '/xrpc/com.atproto.identity.resolveHandle'
        )->withQueryParams(['handle' => 'missing.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        try {
            $action($request, $response, []);
            $this->fail('Expected XrpcException was not thrown.');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('Unable to resolve handle', $e->getMessage());
        }
    }
}
