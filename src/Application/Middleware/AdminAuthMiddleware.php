<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Actions\Pds\XrpcException;
use App\Application\Settings\SettingsInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware that gates an endpoint behind HTTP Basic admin authentication.
 *
 * Requests must include an `Authorization: Basic <base64(admin:password)>`
 * header, where `password` matches the configured `PDS_ADMIN_PASSWORD`.
 *
 * Applies to admin-only endpoints when configured in the route definitions:
 *
 *     $group->post('/com.atproto.server.createInviteCode', CreateInviteCodeAction::class)
 *         ->add(AdminAuthMiddleware::class);
 */
class AdminAuthMiddleware implements Middleware
{
    public const ADMIN_USERNAME = 'admin';

    private string $adminPassword;

    public function __construct(SettingsInterface $settings)
    {
        /** @var array{adminPassword: string} $pdsSettings */
        $pdsSettings = $settings->get('pds');
        $this->adminPassword = $pdsSettings['adminPassword'];
    }

    /**
     * {@inheritdoc}
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || stripos($header, 'Basic ') !== 0) {
            throw XrpcException::authRequired();
        }

        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || !str_contains($decoded, ':')) {
            throw XrpcException::authRequired('Invalid authentication credentials');
        }

        [$username, $password] = explode(':', $decoded, 2);

        $userOk = hash_equals(self::ADMIN_USERNAME, $username);
        $passOk = hash_equals($this->adminPassword, $password);
        if (!$userOk || !$passOk) {
            throw XrpcException::authRequired('Invalid authentication credentials');
        }

        return $handler->handle($request);
    }
}
