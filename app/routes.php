<?php

declare(strict_types=1);

use App\Application\Actions\Pds\Atproto\Server\DescribeServerAction;
use Composer\InstalledVersions;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    $app->get('/', function (Request $request, Response $response) {
        $asciiArt = <<<ASCII
       _               _
      | |             | |
 _ __ | |__  _ __   __| |___
| '_ \| '_ \| '_ \ / _` / __|
| |_) | | | | |_) | (_| \__ \
| .__/|_| |_| .__/ \__,_|___/
| |         | |
|_|         |_|
ASCII;

        $response->getBody()->write("<pre>{$asciiArt}</pre>");
        $response->getBody()->write("<p>this is phpds, an atproto personal data server implemented in PHP!</p>");
        $response->getBody()->write("<p>useful routes are under /xrpc/</p>");
        $response->getBody()->write("<p>please don't use this in prod!</p>");
        $response->getBody()->write("<p>with love, <a href='https://bsky.app/profile/andresitorresm.com'>@andresitorresm.com</a></p>");
        return $response;
    });

    $app->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write('ok');
        return $response;
    });

    $app->group('/xrpc', function (Group $group) {
        // atproto server
        $group->get('/com.atproto.server.describeServer', DescribeServerAction::class);

        // misc
        $group->get('/_health', function (Request $request, Response $response) {
            $version = InstalledVersions::getRootPackage()['pretty_version'] ?? 'unknown';
            $response->getBody()->write(json_encode(['version' => $version]));

            return $response->withHeader('Content-Type', 'application/json');
        });
    });
};
