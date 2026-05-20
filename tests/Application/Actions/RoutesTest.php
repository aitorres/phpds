<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\Pds\XrpcException;
use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\MethodNotAllowedErrorHandler;
use App\Application\Handlers\XrpcErrorHandler;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/health');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('ok', (string) $response->getBody());
    }

    public function testXrpcHealthEndpointReturnsVersion(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/xrpc/_health');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('version', (string) $response->getBody());
    }

    public function testRobotsTxtEndpointReturnsRobotsTxt(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/robots.txt');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('User-agent: *', (string) $response->getBody());
        $this->assertStringContainsString('Allow: /', (string) $response->getBody());
    }

    public function testRootEndpointReturnsWelcomePage(): void
    {
        $app = $this->getAppInstance();
        $request = $this->createRequest('GET', '/');
        $response = $app->handle($request);

        $this->assertEquals(200, $response->getStatusCode());

        $body = (string) $response->getBody();
        $this->assertStringContainsString('phpds', $body);
        $this->assertStringContainsString('atproto personal data server', $body);
        $this->assertStringContainsString('/xrpc/', $body);
    }

    public function testXrpcMethodNotAllowedReturnsXrpcErrorPayload(): void
    {
        $app = $this->getAppInstance();
        $this->configureErrorHandling($app);

        $request = $this->createRequest('POST', '/xrpc/com.atproto.server.describeServer');
        $response = $app->handle($request);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, OPTIONS', $response->getHeaderLine('Allow'));
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            [
                'error' => 'MethodNotAllowed',
                'message' => 'Method not allowed. Must be one of: GET, OPTIONS',
            ],
            json_decode((string) $response->getBody(), true)
        );
    }

    public function testNonXrpcMethodNotAllowedKeepsDefaultApiErrorPayload(): void
    {
        $app = $this->getAppInstance();
        $this->configureErrorHandling($app);

        $request = $this->createRequest('POST', '/health');
        $response = $app->handle($request);

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, OPTIONS', $response->getHeaderLine('Allow'));
        /** @var array{error?: mixed} $payload */
        $payload = json_decode((string) $response->getBody(), true);
        $error = $payload['error'] ?? null;

        $this->assertIsArray($error);
        $this->assertSame('NOT_ALLOWED', $error['type'] ?? null);
        $this->assertSame(
            'Method not allowed. Must be one of: GET, OPTIONS',
            $error['description'] ?? null
        );
    }

    /**
     * @param App<ContainerInterface|null> $app
     */
    private function configureErrorHandling(App $app): void
    {
        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();

        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware(false, false, false);
        $errorMiddleware->setDefaultErrorHandler(new HttpErrorHandler($callableResolver, $responseFactory));
        $errorMiddleware->setErrorHandler(
            XrpcException::class,
            new XrpcErrorHandler($callableResolver, $responseFactory)
        );
        $errorMiddleware->setErrorHandler(
            HttpMethodNotAllowedException::class,
            new MethodNotAllowedErrorHandler($callableResolver, $responseFactory)
        );
    }
}
