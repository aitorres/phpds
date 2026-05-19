<?php

declare(strict_types=1);

namespace App\Application\Actions\Pds;

use Fig\Http\Message\StatusCodeInterface;
use RuntimeException;
use Throwable;

/**
 * Exception representing an atproto XRPC error response.
 *
 * XRPC errors are serialized as `{"error": "<code>", "message": "<text>"}`
 * with an HTTP status code (typically 400 for client errors).
 *
 * @see https://atproto.com/specs/xrpc#error-responses
 */
class XrpcException extends RuntimeException
{
    private string $error;

    private int $statusCode;

    public function __construct(
        string $error,
        string $message,
        int $statusCode = StatusCodeInterface::STATUS_BAD_REQUEST,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->error = $error;
        $this->statusCode = $statusCode;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public static function missingParam(string $actionName, string $paramName): self
    {
        return self::invalidRequest(
            sprintf(
                'Invalid %s params: Missing required key "%s"',
                $actionName,
                $paramName
            )
        );
    }

    public static function invalidParam(
        string $actionName,
        string $reason,
        string $value
    ): self {
        return self::invalidRequest(
            sprintf(
                'Invalid %s params: %s (got "%s")',
                $actionName,
                $reason,
                $value
            )
        );
    }

    public static function invalidRequest(string $message, string $error = 'InvalidRequest'): self
    {
        return new self($error, $message, StatusCodeInterface::STATUS_BAD_REQUEST);
    }

    public static function authRequired(string $message = 'Authentication required'): self
    {
        return new self('AuthenticationRequired', $message, StatusCodeInterface::STATUS_UNAUTHORIZED);
    }
}
