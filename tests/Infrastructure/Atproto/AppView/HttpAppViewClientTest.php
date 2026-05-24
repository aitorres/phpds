<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Atproto\AppView;

use App\Domain\Pds\Atproto\AppView\AppViewException;
use App\Infrastructure\Atproto\AppView\HttpAppViewClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class HttpAppViewClientTest extends TestCase
{
    /**
     * @param list<Response|\Throwable> $queue
     * @param array<int, array<string, mixed>> $history
     */
    private function makeClient(array $queue, array &$history = []): HttpAppViewClient
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        /** @phpstan-ignore parameterByRef.type */
        $stack->push(Middleware::history($history));

        $httpClient = new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.bsky.app/',
        ]);

        return new HttpAppViewClient($httpClient);
    }

    public function testResolveHandleReturnsDidOnSuccess(): void
    {
        $history = [];
        $client = $this->makeClient(
            [new Response(200, [], (string) json_encode(['did' => 'did:plc:abc']))],
            $history
        );

        $did = $client->resolveHandle('bob.bsky.social');

        $this->assertSame('did:plc:abc', $did);
        $this->assertCount(1, $history);

        /** @var \Psr\Http\Message\RequestInterface $sent */
        $sent = $history[0]['request'];
        $this->assertSame('GET', $sent->getMethod());
        $this->assertSame(
            'https://api.bsky.app/xrpc/com.atproto.identity.resolveHandle',
            (string) $sent->getUri()->withQuery('')
        );
        $this->assertSame('handle=bob.bsky.social', $sent->getUri()->getQuery());
    }

    public function testResolveHandleWrapsNon2xxResponseAsAppViewException(): void
    {
        $request = new Request('GET', 'xrpc/com.atproto.identity.resolveHandle');
        $client = $this->makeClient([
            new \GuzzleHttp\Exception\BadResponseException(
                'Bad Request',
                $request,
                new Response(400, [], (string) json_encode(['error' => 'InvalidRequest', 'message' => 'nope']))
            ),
        ]);

        $this->expectException(AppViewException::class);
        $client->resolveHandle('missing.bsky.social');
    }

    public function testResolveHandleWrapsTransportErrorAsAppViewException(): void
    {
        $request = new Request('GET', 'xrpc/com.atproto.identity.resolveHandle');
        $client = $this->makeClient([
            new ConnectException('connection refused', $request),
        ]);

        $this->expectException(AppViewException::class);
        $client->resolveHandle('bob.bsky.social');
    }

    public function testResolveHandleThrowsWhenDidFieldMissing(): void
    {
        $client = $this->makeClient([
            new Response(200, [], (string) json_encode(['notTheDid' => 'oops'])),
        ]);

        $this->expectException(AppViewException::class);
        $client->resolveHandle('bob.bsky.social');
    }

    public function testResolveHandleThrowsWhenResponseIsNotJson(): void
    {
        $client = $this->makeClient([
            new Response(200, [], 'this is not json'),
        ]);

        $this->expectException(AppViewException::class);
        $client->resolveHandle('bob.bsky.social');
    }

    public function testResolveHandleThrowsWhenDidIsNotAString(): void
    {
        $client = $this->makeClient([
            new Response(200, [], (string) json_encode(['did' => 123])),
        ]);

        $this->expectException(AppViewException::class);
        $client->resolveHandle('bob.bsky.social');
    }
}
