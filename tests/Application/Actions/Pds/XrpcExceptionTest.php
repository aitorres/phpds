<?php

declare(strict_types=1);

namespace Tests\Application\Actions\Pds;

use App\Application\Actions\Pds\XrpcException;
use Tests\TestCase;

class XrpcExceptionTest extends TestCase
{
    public function testConstructorDefaultsToBadRequestStatus(): void
    {
        $exception = new XrpcException('InvalidRequest', 'boom');

        $this->assertSame('InvalidRequest', $exception->getError());
        $this->assertSame('boom', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
    }

    public function testConstructorAcceptsCustomStatusCode(): void
    {
        $exception = new XrpcException('AuthRequired', 'missing token', 401);

        $this->assertSame('AuthRequired', $exception->getError());
        $this->assertSame(401, $exception->getStatusCode());
    }

    public function testInvalidRequestUsesInvalidRequestErrorCode(): void
    {
        $exception = XrpcException::invalidRequest('something is off');

        $this->assertSame('InvalidRequest', $exception->getError());
        $this->assertSame('something is off', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
    }

    public function testInvalidRequestSupportsCustomErrorCode(): void
    {
        $exception = XrpcException::invalidRequest('Handle too short', 'InvalidHandle');

        $this->assertSame('InvalidHandle', $exception->getError());
        $this->assertSame('Handle too short', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
    }

    public function testMissingParamFormatsMessage(): void
    {
        $exception = XrpcException::missingParam('com.atproto.foo.bar', 'baz');

        $this->assertSame('InvalidRequest', $exception->getError());
        $this->assertSame(
            'Invalid com.atproto.foo.bar params: Missing required key "baz"',
            $exception->getMessage()
        );
        $this->assertSame(400, $exception->getStatusCode());
    }

    public function testInvalidParamFormatsMessageWithValue(): void
    {
        $exception = XrpcException::invalidParam(
            'com.atproto.identity.resolveHandle',
            'Invalid handle',
            'a'
        );

        $this->assertSame('InvalidRequest', $exception->getError());
        $this->assertSame(
            'Invalid com.atproto.identity.resolveHandle params: Invalid handle (got "a")',
            $exception->getMessage()
        );
        $this->assertSame(400, $exception->getStatusCode());
    }
}
