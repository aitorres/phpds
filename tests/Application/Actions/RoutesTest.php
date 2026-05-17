<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

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
}
