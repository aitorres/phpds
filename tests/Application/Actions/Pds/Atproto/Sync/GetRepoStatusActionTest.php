<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Sync;

use App\Application\Actions\Pds\Atproto\Sync\GetRepoStatusAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;
use App\Domain\ActorStore\ActorStore;
use App\Domain\ActorStore\ActorStoreFactory;
use App\Domain\Repo\RepoRoot;
use App\Domain\Repo\RepoRootNotFoundException;
use App\Domain\Repo\RepoRootRepository;
use DateTimeImmutable;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class GetRepoStatusActionTest extends TestCase
{
    private function makeActor(
        string $did,
        ?DateTimeImmutable $deactivatedAt = null,
        ?string $takedownRef = null
    ): Actor {
        return new Actor(
            did: $did,
            handle: 'alice.pds.test',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: $takedownRef,
            deactivatedAt: $deactivatedAt,
        );
    }

    /**
     * Returns an ActorStoreFactory that resolves $did -> $root, or throws
     * RepoRootNotFoundException when $root is null.
     */
    private function makeFactory(string $did, ?RepoRoot $root): ActorStoreFactory
    {
        $repoRootProphecy = $this->prophesize(RepoRootRepository::class);
        if ($root === null) {
            $repoRootProphecy->findByDid($did)->willThrow(new RepoRootNotFoundException());
        } else {
            $repoRootProphecy->findByDid($did)->willReturn($root);
        }

        $storeProphecy = $this->prophesize(ActorStore::class);
        $storeProphecy->getRepoRoot()->willReturn($repoRootProphecy->reveal());

        $factoryProphecy = $this->prophesize(ActorStoreFactory::class);
        $factoryProphecy->get($did)->willReturn($storeProphecy->reveal());

        return $factoryProphecy->reveal();
    }

    public function testActionReturnsActiveStatusWithRevForLiveRepo(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor('did:web:alice.pds.test');
        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:alice.pds.test')
            ->willReturn($actor)
            ->shouldBeCalledOnce();

        $root = new RepoRoot(
            'did:web:alice.pds.test',
            'bafyHead',
            '3kabc',
            new DateTimeImmutable('2026-01-02T00:00:00Z')
        );
        $factory = $this->makeFactory('did:web:alice.pds.test', $root);

        $action = new GetRepoStatusAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:alice.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expected = json_encode(
            [
                'did'    => 'did:web:alice.pds.test',
                'active' => true,
                'rev'    => '3kabc',
            ],
            JSON_PRETTY_PRINT
        );

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame('application/json', $actualResponse->getHeaderLine('Content-Type'));
        $this->assertSame($expected, (string) $actualResponse->getBody());
    }

    public function testActionOmitsRevWhenActiveRepoHasNoRoot(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor('did:web:newbie.pds.test');
        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:newbie.pds.test')->willReturn($actor);

        $factory = $this->makeFactory('did:web:newbie.pds.test', null);

        $action = new GetRepoStatusAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:newbie.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expected = json_encode(
            [
                'did'    => 'did:web:newbie.pds.test',
                'active' => true,
            ],
            JSON_PRETTY_PRINT
        );

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame($expected, (string) $actualResponse->getBody());
    }

    public function testActionReturnsTakendownStatusWithoutRev(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor('did:web:banned.pds.test', null, 'mod-action-123');
        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:banned.pds.test')->willReturn($actor);

        $factory = $this->prophesize(ActorStoreFactory::class);
        $factory->get(Argument::any())->shouldNotBeCalled();

        $action = new GetRepoStatusAction(
            $logger,
            $settings,
            $repoProphecy->reveal(),
            $factory->reveal()
        );

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:banned.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expected = json_encode(
            [
                'did'    => 'did:web:banned.pds.test',
                'active' => false,
                'status' => 'takendown',
            ],
            JSON_PRETTY_PRINT
        );

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame($expected, (string) $actualResponse->getBody());
    }

    public function testActionReturnsDeactivatedStatusWithoutRev(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor(
            'did:web:gone.pds.test',
            new DateTimeImmutable('2026-02-01T00:00:00Z')
        );
        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:gone.pds.test')->willReturn($actor);

        $factory = $this->prophesize(ActorStoreFactory::class);
        $factory->get(Argument::any())->shouldNotBeCalled();

        $action = new GetRepoStatusAction(
            $logger,
            $settings,
            $repoProphecy->reveal(),
            $factory->reveal()
        );

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:gone.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expected = json_encode(
            [
                'did'    => 'did:web:gone.pds.test',
                'active' => false,
                'status' => 'deactivated',
            ],
            JSON_PRETTY_PRINT
        );

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame($expected, (string) $actualResponse->getBody());
    }

    public function testActionPrefersTakedownOverDeactivation(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor(
            'did:web:both.pds.test',
            new DateTimeImmutable('2026-02-01T00:00:00Z'),
            'mod-action-123'
        );
        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:both.pds.test')->willReturn($actor);

        $factory = $this->prophesize(ActorStoreFactory::class);
        $factory->get(Argument::any())->shouldNotBeCalled();

        $action = new GetRepoStatusAction(
            $logger,
            $settings,
            $repoProphecy->reveal(),
            $factory->reveal()
        );

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:both.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        /** @var array{status?: string} $payload */
        $payload = json_decode((string) $actualResponse->getBody(), true);
        $this->assertSame('takendown', $payload['status'] ?? null);
    }

    public function testActionThrowsRepoNotFoundWhenActorMissing(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findActorByDid('did:web:missing.pds.test')
            ->willThrow(new ActorNotFoundException());

        $factory = $this->prophesize(ActorStoreFactory::class);
        $factory->get(Argument::any())->shouldNotBeCalled();

        $action = new GetRepoStatusAction(
            $logger,
            $settings,
            $repoProphecy->reveal(),
            $factory->reveal()
        );

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'did:web:missing.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        try {
            $action($request, $response, []);
            $this->fail('Expected XrpcException was not thrown.');
        } catch (XrpcException $e) {
            $this->assertSame('RepoNotFound', $e->getError());
            $this->assertSame(400, $e->getStatusCode());
        }
    }

    public function testActionThrowsXrpcInvalidRequestWhenDidParamMissing(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repo = $this->prophesize(ActorRepository::class);
        $repo->findActorByDid(Argument::any())->shouldNotBeCalled();
        $factory = $this->prophesize(ActorStoreFactory::class)->reveal();

        $action = new GetRepoStatusAction($logger, $settings, $repo->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus');
        $response = (new ResponseFactory())->createResponse();

        try {
            $action($request, $response, []);
            $this->fail('Expected XrpcException was not thrown.');
        } catch (XrpcException $e) {
            $this->assertSame('InvalidRequest', $e->getError());
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame(
                'Invalid com.atproto.sync.getRepoStatus params: Missing required key "did"',
                $e->getMessage()
            );
        }
    }

    public function testActionThrowsXrpcInvalidParamWhenDidIsMalformed(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repo = $this->prophesize(ActorRepository::class);
        $repo->findActorByDid(Argument::any())->shouldNotBeCalled();
        $factory = $this->prophesize(ActorStoreFactory::class)->reveal();

        $action = new GetRepoStatusAction($logger, $settings, $repo->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.getRepoStatus')
            ->withQueryParams(['did' => 'not-a-did']);
        $response = (new ResponseFactory())->createResponse();

        $this->expectException(XrpcException::class);
        $action($request, $response, []);
    }
}
