<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Sync;

use App\Application\Actions\Pds\Atproto\Sync\ListReposAction;
use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\Settings;
use App\Domain\Actor\Actor;
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

class ListReposActionTest extends TestCase
{
    private function makeActor(
        string $did,
        ?\DateTimeImmutable $deactivatedAt = null,
        ?string $takedownRef = null
    ): Actor {
        return new Actor(
            did: $did,
            handle: substr($did, strrpos($did, ':') + 1) . '.pds.test',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: $takedownRef,
            deactivatedAt: $deactivatedAt,
        );
    }

    /**
     * Wire an ActorStoreFactory that returns ActorStores whose RepoRoot
     * repository resolves $did -> $root (or throws when $root is null).
     *
     * @param array<string, RepoRoot|null> $rootsByDid
     */
    private function makeFactory(array $rootsByDid): ActorStoreFactory
    {
        $factoryProphecy = $this->prophesize(ActorStoreFactory::class);

        foreach ($rootsByDid as $did => $root) {
            $repoRootProphecy = $this->prophesize(RepoRootRepository::class);
            if ($root === null) {
                $repoRootProphecy->findByDid($did)->willThrow(new RepoRootNotFoundException());
            } else {
                $repoRootProphecy->findByDid($did)->willReturn($root);
            }

            $storeProphecy = $this->prophesize(ActorStore::class);
            $storeProphecy->getRepoRoot()->willReturn($repoRootProphecy->reveal());

            $factoryProphecy->get($did)->willReturn($storeProphecy->reveal());
        }

        return $factoryProphecy->reveal();
    }

    public function testActionReturnsAllReposWhenUnderLimit(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $alice = $this->makeActor('did:web:alice.pds.test');
        $bob   = $this->makeActor('did:web:bob.pds.test', new DateTimeImmutable('2026-02-01T00:00:00Z'));

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, ListReposAction::DEFAULT_LIMIT)
            ->willReturn([$alice, $bob])
            ->shouldBeCalledOnce();

        $factory = $this->makeFactory([
            'did:web:alice.pds.test' => new RepoRoot(
                'did:web:alice.pds.test',
                'bafyAliceHead',
                '3kabc',
                new DateTimeImmutable('2026-01-02T00:00:00Z')
            ),
            'did:web:bob.pds.test' => new RepoRoot(
                'did:web:bob.pds.test',
                'bafyBobHead',
                '3kxyz',
                new DateTimeImmutable('2026-01-03T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expectedPayload = json_encode([
            'repos' => [
                [
                    'did' => 'did:web:alice.pds.test',
                    'head' => 'bafyAliceHead',
                    'rev' => '3kabc',
                    'active' => true,
                ],
                [
                    'did' => 'did:web:bob.pds.test',
                    'head' => 'bafyBobHead',
                    'rev' => '3kxyz',
                    'active' => false,
                    'status' => 'deactivated',
                ],
            ],
        ], JSON_PRETTY_PRINT);

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame('application/json', $actualResponse->getHeaderLine('Content-Type'));
        $this->assertSame($expectedPayload, (string) $actualResponse->getBody());
    }

    public function testActionEmitsCursorWhenPageIsFull(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $alice = $this->makeActor('did:web:alice.pds.test');
        $bob   = $this->makeActor('did:web:bob.pds.test');

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, 2)
            ->willReturn([$alice, $bob])
            ->shouldBeCalledOnce();

        $factory = $this->makeFactory([
            'did:web:alice.pds.test' => new RepoRoot(
                'did:web:alice.pds.test',
                'bafyAliceHead',
                '3kabc',
                new DateTimeImmutable('2026-01-02T00:00:00Z')
            ),
            'did:web:bob.pds.test' => new RepoRoot(
                'did:web:bob.pds.test',
                'bafyBobHead',
                '3kxyz',
                new DateTimeImmutable('2026-01-03T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos')
            ->withQueryParams(['limit' => '2']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertIsArray($body);
        $repos = $body['repos'] ?? null;
        $this->assertIsArray($repos);
        $this->assertSame('did:web:bob.pds.test', $body['cursor']);
        $this->assertCount(2, $repos);
    }

    public function testActionAdvancesUsingProvidedCursor(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $bob = $this->makeActor('did:web:bob.pds.test');

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage('did:web:alice.pds.test', ListReposAction::DEFAULT_LIMIT)
            ->willReturn([$bob])
            ->shouldBeCalledOnce();

        $factory = $this->makeFactory([
            'did:web:bob.pds.test' => new RepoRoot(
                'did:web:bob.pds.test',
                'bafyBobHead',
                '3kxyz',
                new DateTimeImmutable('2026-01-03T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos')
            ->withQueryParams(['cursor' => 'did:web:alice.pds.test']);
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertIsArray($body);
        $repos = $body['repos'] ?? null;
        $this->assertIsArray($repos);
        $this->assertArrayNotHasKey('cursor', $body);
        $this->assertCount(1, $repos);
        $first = $repos[0] ?? null;
        $this->assertIsArray($first);
        $this->assertSame('did:web:bob.pds.test', $first['did']);
    }

    public function testActionSkipsActorsWithoutRepoRoot(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $alice = $this->makeActor('did:web:alice.pds.test');
        $bob   = $this->makeActor('did:web:bob.pds.test');

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, ListReposAction::DEFAULT_LIMIT)
            ->willReturn([$alice, $bob]);

        $factory = $this->makeFactory([
            'did:web:alice.pds.test' => null,
            'did:web:bob.pds.test' => new RepoRoot(
                'did:web:bob.pds.test',
                'bafyBobHead',
                '3kxyz',
                new DateTimeImmutable('2026-01-03T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertIsArray($body);
        $repos = $body['repos'] ?? null;
        $this->assertIsArray($repos);
        $this->assertCount(1, $repos);
        $first = $repos[0] ?? null;
        $this->assertIsArray($first);
        $this->assertSame('did:web:bob.pds.test', $first['did']);
    }

    public function testActionRejectsLimitOutOfRange(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repo = $this->prophesize(ActorRepository::class);
        $repo->findPage(Argument::any(), Argument::any())->shouldNotBeCalled();
        $factory = $this->prophesize(ActorStoreFactory::class)->reveal();

        $action = new ListReposAction($logger, $settings, $repo->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos')
            ->withQueryParams(['limit' => '0']);
        $response = (new ResponseFactory())->createResponse();

        $this->expectException(XrpcException::class);
        $action($request, $response, []);
    }

    public function testActionRejectsNonIntegerLimit(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);
        $repo = $this->prophesize(ActorRepository::class);
        $repo->findPage(Argument::any(), Argument::any())->shouldNotBeCalled();
        $factory = $this->prophesize(ActorStoreFactory::class)->reveal();

        $action = new ListReposAction($logger, $settings, $repo->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos')
            ->withQueryParams(['limit' => 'banana']);
        $response = (new ResponseFactory())->createResponse();

        $this->expectException(XrpcException::class);
        $action($request, $response, []);
    }

    public function testActionMarksTakendownActorsAsInactiveWithTakedownStatus(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $banned = $this->makeActor('did:web:banned.pds.test', null, 'mod-action-123');

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, ListReposAction::DEFAULT_LIMIT)
            ->willReturn([$banned]);

        $factory = $this->makeFactory([
            'did:web:banned.pds.test' => new RepoRoot(
                'did:web:banned.pds.test',
                'bafyBannedHead',
                '3kbanned',
                new DateTimeImmutable('2026-01-04T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertIsArray($body);
        $repos = $body['repos'] ?? null;
        $this->assertIsArray($repos);
        $first = $repos[0] ?? null;
        $this->assertIsArray($first);
        $this->assertSame('did:web:banned.pds.test', $first['did']);
        $this->assertFalse($first['active']);
        $this->assertSame('takendown', $first['status']);
    }

    public function testActionPrefersTakedownStatusOverDeactivation(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $actor = $this->makeActor(
            'did:web:both.pds.test',
            new DateTimeImmutable('2026-02-01T00:00:00Z'),
            'mod-action-123'
        );

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, ListReposAction::DEFAULT_LIMIT)
            ->willReturn([$actor]);

        $factory = $this->makeFactory([
            'did:web:both.pds.test' => new RepoRoot(
                'did:web:both.pds.test',
                'bafyHead',
                '3kboth',
                new DateTimeImmutable('2026-01-05T00:00:00Z')
            ),
        ]);

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertIsArray($body);
        $repos = $body['repos'] ?? null;
        $this->assertIsArray($repos);
        $first = $repos[0] ?? null;
        $this->assertIsArray($first);
        $this->assertSame('takendown', $first['status']);
    }

    public function testActionReturnsEmptyListWhenNoActors(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([]);

        $repoProphecy = $this->prophesize(ActorRepository::class);
        $repoProphecy->findPage(null, ListReposAction::DEFAULT_LIMIT)->willReturn([]);

        $factory = $this->prophesize(ActorStoreFactory::class);
        $factory->get(Argument::any())->shouldNotBeCalled();

        $action = new ListReposAction($logger, $settings, $repoProphecy->reveal(), $factory->reveal());

        $request = $this->createRequest('GET', '/xrpc/com.atproto.sync.listRepos');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $body = json_decode((string) $actualResponse->getBody(), true);
        $this->assertSame(['repos' => []], $body);
    }
}
