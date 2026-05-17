<?php

declare(strict_types=1);

namespace App\Application\Handlers;

use App\Application\Actions\Pds\XrpcException;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

/**
 * Error handler that renders exceptions in the atproto XRPC error format:
 *     {"error": "<code>", "message": "<text>"}
 */
class XrpcErrorHandler extends SlimErrorHandler
{
    /**
     * @inheritdoc
     */
    protected function respond(): Response
    {
        $exception = $this->exception;
        $statusCode = 500;
        $error = 'InternalServerError';
        $message = 'An internal error has occurred while processing your request.';

        if ($exception instanceof XrpcException) {
            $statusCode = $exception->getStatusCode();
            $error = $exception->getError();
            $message = $exception->getMessage();
        } elseif ($this->displayErrorDetails) {
            $message = $exception->getMessage();
        }

        $payload = (string) json_encode(
            ['error' => $error, 'message' => $message],
            JSON_PRETTY_PRINT
        );

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write($payload);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
