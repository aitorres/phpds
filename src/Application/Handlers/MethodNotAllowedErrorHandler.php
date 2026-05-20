<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

class MethodNotAllowedErrorHandler extends SlimErrorHandler
{
    /**
     * @inheritdoc
     */
    protected function respond(): Response
    {
        $exception = $this->exception;
        $response = $this->responseFactory->createResponse(405);

        if ($exception instanceof HttpMethodNotAllowedException) {
            $response = $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        }

        if ($this->isXrpcRequest()) {
            $payload = (string) json_encode(
                [
                    'error' => 'MethodNotAllowed',
                    'message' => $exception->getMessage(),
                ],
                JSON_PRETTY_PRINT
            );

            $response->getBody()->write($payload);

            return $response->withHeader('Content-Type', 'application/json');
        }

        $payload = new ActionPayload(
            405,
            null,
            new ActionError(ActionError::NOT_ALLOWED, $exception->getMessage())
        );
        $encodedPayload = (string) json_encode($payload, JSON_PRETTY_PRINT);

        $response->getBody()->write($encodedPayload);

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isXrpcRequest(): bool
    {
        $path = $this->request->getUri()->getPath();

        return $path === '/xrpc' || str_starts_with($path, '/xrpc/');
    }
}
