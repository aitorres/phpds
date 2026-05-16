<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds\Atproto\Server;

use App\Application\Actions\Pds\Atproto\Server\DescribeServerAction;
use App\Application\Settings\Settings;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\TestCase;

class DescribeServerActionTest extends TestCase
{
    public function testActionReturnsDescribeServerPayload(): void
    {
        $logger = $this->prophesize(LoggerInterface::class)->reveal();
        $settings = new Settings([
            'pds' => [
                'hostname' => 'pds.test',
                'privacyPolicyUrl' => 'https://pds.test/privacy',
                'termsOfServiceUrl' => 'https://pds.test/tos',
                'email' => 'admin@pds.test',
            ],
        ]);
        $action = new DescribeServerAction($logger, $settings);

        $request = $this->createRequest('GET', '/xrpc/com.atproto.server.describeServer');
        $response = (new ResponseFactory())->createResponse();

        $actualResponse = $action($request, $response, []);

        $expectedPayload = json_encode([
            'did' => 'did:web:pds.test',
            'inviteCodeRequired' => true,
            'availableUserDomains' => ['.pds.test'],
            'contact' => [
                'email' => 'admin@pds.test',
            ],
            'links' => [
                'termsOfService' => 'https://pds.test/tos',
                'privacyPolicy' => 'https://pds.test/privacy',
            ],
        ], JSON_PRETTY_PRINT);

        $this->assertSame(200, $actualResponse->getStatusCode());
        $this->assertSame('application/json', $actualResponse->getHeaderLine('Content-Type'));
        $this->assertSame($expectedPayload, (string) $actualResponse->getBody());
    }
}