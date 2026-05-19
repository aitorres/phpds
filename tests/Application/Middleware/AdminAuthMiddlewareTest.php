<?php

declare(strict_types=1);

namespace Tests\Application\Middleware;

use App\Application\Actions\Pds\XrpcException;
use App\Application\Middleware\AdminAuthMiddleware;
use App\Application\Settings\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class AdminAuthMiddlewareTest extends TestCase
{
    private const ADMIN_PASSWORD = 'super-secret';

    private function makeMiddleware(): AdminAuthMiddleware
    {
        $settings = new Settings(['pds' => ['adminPassword' => self::ADMIN_PASSWORD]]);
        return new AdminAuthMiddleware($settings);
    }

    private function makeHandler(): RequestHandler
    {
        return new class implements RequestHandler {
            public function handle(Request $request): Response
            {
                $response = (new ResponseFactory())->createResponse(200);
                $response->getBody()->write('ok');
                return $response;
            }
        };
    }

    public function testRequestWithValidCredentialsPassesThrough(): void
    {
        $credentials = base64_encode('admin:' . self::ADMIN_PASSWORD);
        $request = $this->createRequest('POST', '/protected')
            ->withHeader('Authorization', 'Basic ' . $credentials);

        $response = $this->makeMiddleware()->process($request, $this->makeHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response->getBody());
    }

    public function testRequestWithoutAuthorizationHeaderIsRejected(): void
    {
        $request = $this->createRequest('POST', '/protected');

        $this->expectException(XrpcException::class);
        $this->expectExceptionMessage('Authentication required');

        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testRequestWithWrongPasswordIsRejected(): void
    {
        $credentials = base64_encode('admin:not-the-password');
        $request = $this->createRequest('POST', '/protected')
            ->withHeader('Authorization', 'Basic ' . $credentials);

        try {
            $this->makeMiddleware()->process($request, $this->makeHandler());
            $this->fail('Expected XrpcException was not thrown');
        } catch (XrpcException $e) {
            $this->assertSame('AuthenticationRequired', $e->getError());
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function testRequestWithWrongUsernameIsRejected(): void
    {
        $credentials = base64_encode('root:' . self::ADMIN_PASSWORD);
        $request = $this->createRequest('POST', '/protected')
            ->withHeader('Authorization', 'Basic ' . $credentials);

        $this->expectException(XrpcException::class);
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testRequestWithNonBasicSchemeIsRejected(): void
    {
        $request = $this->createRequest('POST', '/protected')
            ->withHeader('Authorization', 'Bearer some-token');

        $this->expectException(XrpcException::class);
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }

    public function testRequestWithMalformedBasicPayloadIsRejected(): void
    {
        $request = $this->createRequest('POST', '/protected')
            ->withHeader('Authorization', 'Basic !!!not-base64!!!');

        $this->expectException(XrpcException::class);
        $this->makeMiddleware()->process($request, $this->makeHandler());
    }
}
