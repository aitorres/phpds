<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Did;

use App\Domain\Did\DidCacheRepository;
use App\Domain\Did\DidDocEntry;
use App\Domain\Did\DidDocEntryNotFoundException;
use App\Infrastructure\Did\HttpDidResolver;
use DateTimeImmutable;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Tests\TestCase;

class HttpDidResolverTest extends TestCase
{
    /** @var ObjectProphecy<ClientInterface> */
    private ObjectProphecy $httpClient;
    /** @var ObjectProphecy<DidCacheRepository> */
    private ObjectProphecy $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = $this->prophesize(ClientInterface::class);
        $this->cache      = $this->prophesize(DidCacheRepository::class);
    }

    private function resolver(string $plcUrl = 'https://plc.directory'): HttpDidResolver
    {
        return new HttpDidResolver($this->httpClient->reveal(), $this->cache->reveal(), $plcUrl);
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function cachedEntry(string $did, array $doc): DidDocEntry
    {
        return new DidDocEntry($did, $doc, new DateTimeImmutable());
    }

    public function testReturnsCachedDocWithoutHttpCall(): void
    {
        $doc = ['id' => 'did:plc:alice'];
        $this->cache->has('did:plc:alice')->willReturn(true);
        $this->cache->get('did:plc:alice')->willReturn($this->cachedEntry('did:plc:alice', $doc));
        $this->httpClient->request(Argument::cetera())->shouldNotBeCalled();

        $result = $this->resolver()->resolve('did:plc:alice');
        $this->assertSame($doc, $result);
    }

    public function testFetchesPlcDidFromDirectory(): void
    {
        $doc = ['id' => 'did:plc:alice'];
        $this->cache->has('did:plc:alice')->willReturn(false);
        $this->httpClient
            ->request('GET', 'https://plc.directory/did%3Aplc%3Aalice', Argument::any())
            ->willReturn(new Response(200, [], (string) json_encode($doc)))
            ->shouldBeCalledOnce();
        $this->cache->set('did:plc:alice', $doc)->shouldBeCalledOnce();

        $result = $this->resolver()->resolve('did:plc:alice');
        $this->assertSame($doc, $result);
    }

    public function testFetchesWebDidFromWellKnown(): void
    {
        $doc = ['id' => 'did:web:example.com'];
        $this->cache->has('did:web:example.com')->willReturn(false);
        $this->httpClient
            ->request('GET', 'https://example.com/.well-known/did.json', Argument::any())
            ->willReturn(new Response(200, [], (string) json_encode($doc)))
            ->shouldBeCalledOnce();
        $this->cache->set('did:web:example.com', $doc)->shouldBeCalledOnce();

        $result = $this->resolver()->resolve('did:web:example.com');
        $this->assertSame($doc, $result);
    }

    public function testReturnsNullForUnsupportedDidMethod(): void
    {
        $this->cache->has('did:key:abc123')->willReturn(false);
        $this->httpClient->request(Argument::cetera())->shouldNotBeCalled();
        $this->cache->set(Argument::cetera())->shouldNotBeCalled();

        $result = $this->resolver()->resolve('did:key:abc123');
        $this->assertNull($result);
    }

    public function testReturnsNullOnHttpFailure(): void
    {
        $this->cache->has('did:plc:alice')->willReturn(false);
        $this->httpClient
            ->request(Argument::cetera())
            ->willThrow(new \RuntimeException('Connection refused'));
        $this->cache->set(Argument::cetera())->shouldNotBeCalled();

        $result = $this->resolver()->resolve('did:plc:alice');
        $this->assertNull($result);
    }

    public function testReturnsNullOnInvalidJsonResponse(): void
    {
        $this->cache->has('did:plc:alice')->willReturn(false);
        $this->httpClient
            ->request(Argument::cetera())
            ->willReturn(new Response(200, [], 'not-json'));
        $this->cache->set(Argument::cetera())->shouldNotBeCalled();

        $result = $this->resolver()->resolve('did:plc:alice');
        $this->assertNull($result);
    }

    public function testFallsThroughToCacheOnEntryNotFoundException(): void
    {
        $doc = ['id' => 'did:plc:alice'];
        $this->cache->has('did:plc:alice')->willReturn(true);
        $this->cache->get('did:plc:alice')->willThrow(new DidDocEntryNotFoundException());
        $this->httpClient
            ->request('GET', Argument::containingString('did%3Aplc%3Aalice'), Argument::any())
            ->willReturn(new Response(200, [], (string) json_encode($doc)));
        $this->cache->set('did:plc:alice', $doc)->shouldBeCalledOnce();

        $result = $this->resolver()->resolve('did:plc:alice');
        $this->assertSame($doc, $result);
    }

    public function testCustomPlcDirectoryUrlIsUsed(): void
    {
        $doc = ['id' => 'did:plc:alice'];
        $this->cache->has('did:plc:alice')->willReturn(false);
        $this->httpClient
            ->request('GET', 'https://custom.plc.example/did%3Aplc%3Aalice', Argument::any())
            ->willReturn(new Response(200, [], (string) json_encode($doc)))
            ->shouldBeCalledOnce();
        $this->cache->set(Argument::cetera())->shouldBeCalledOnce();

        $this->resolver('https://custom.plc.example')->resolve('did:plc:alice');
    }
}
