<?php

declare(strict_types=1);

use App\Application\Actions\Pds\Atproto\Admin\GetInviteCodesAction;
use App\Application\Actions\Pds\Atproto\Identity\ResolveHandleAction;
use App\Application\Actions\Pds\Atproto\Server\CreateInviteCodeAction;
use App\Application\Actions\Pds\Atproto\Server\DescribeServerAction;
use App\Application\Actions\Pds\Atproto\Sync\GetLatestCommitAction;
use App\Application\Actions\Pds\Atproto\Sync\ListReposAction;
use App\Application\Middleware\AdminAuthMiddleware;
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
        $response
            ->getBody()
            ->write("<p>with love, <a href='https://bsky.app/profile/andresitorresm.com'>@andresitorresm.com</a></p>");
        $response
            ->getBody()
            ->write(
                "<p>source code: "
                . "<a href='https://github.com/aitorres/phpds'>GitHub</a> and "
                . "<a href='https://tangled.sh/andresitorresm.com/phpds'>tangled.sh</a></p>"
            );
        return $response;
    });

    $app->get('/health', function (Request $request, Response $response) {
        $response->getBody()->write('ok');
        return $response;
    });

    $app->get('/robots.txt', function (Request $request, Response $response) {
        $response->getBody()->write("User-agent: *\nAllow: /");
        return $response->withHeader('Content-Type', 'text/plain');
    });

    $app->group('/xrpc', function (Group $group) {
        // atproto admin
        $group->get('/com.atproto.admin.getInviteCodes', GetInviteCodesAction::class)
            ->add(AdminAuthMiddleware::class);

        // atproto server
        $group->post('/com.atproto.server.createInviteCode', CreateInviteCodeAction::class)
            ->add(AdminAuthMiddleware::class);
        $group->get('/com.atproto.server.describeServer', DescribeServerAction::class);

        // atproto identity
        $group->get('/com.atproto.identity.resolveHandle', ResolveHandleAction::class);

        // atproto sync
        $group->get('/com.atproto.sync.listRepos', ListReposAction::class);
        $group->get('/com.atproto.sync.getLatestCommit', GetLatestCommitAction::class);

        // misc
        $group->get('/_health', function (Request $request, Response $response) {
            $version = InstalledVersions::getRootPackage()['pretty_version'];
            $response->getBody()->write((string) json_encode(['version' => $version]));

            return $response->withHeader('Content-Type', 'application/json');
        });
    });
};
