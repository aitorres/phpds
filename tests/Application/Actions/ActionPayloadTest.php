<?php

declare(strict_types=1);

namespace Tests\Application\Actions;

use App\Application\Actions\ActionError;
use App\Application\Actions\ActionPayload;
use JsonSerializable;
use Tests\TestCase;

class ActionPayloadTest extends TestCase
{
    public function testDefaults(): void
    {
        $payload = new ActionPayload();

        $this->assertSame(200, $payload->getStatusCode());
        $this->assertNull($payload->getData());
        $this->assertNull($payload->getError());
    }

    public function testGettersWithAllFields(): void
    {
        $error = new ActionError(ActionError::BAD_REQUEST, 'bad');
        $data = ['hello' => 'world'];

        $payload = new ActionPayload(400, $data, $error);

        $this->assertSame(400, $payload->getStatusCode());
        $this->assertSame($data, $payload->getData());
        $this->assertSame($error, $payload->getError());
    }

    public function testJsonSerializeWithArrayDataIncludesStatusAndData(): void
    {
        $payload = new ActionPayload(201, ['id' => 1]);

        $this->assertSame(
            ['statusCode' => 201, 'data' => ['id' => 1]],
            $payload->jsonSerialize()
        );
    }

    public function testJsonSerializeOmitsNullDataAndIncludesError(): void
    {
        $error = new ActionError(ActionError::SERVER_ERROR, 'kaboom');
        $payload = new ActionPayload(500, null, $error);

        $this->assertSame(
            ['statusCode' => 500, 'error' => $error],
            $payload->jsonSerialize()
        );
    }

    public function testJsonSerializeDelegatesToJsonSerializableData(): void
    {
        $data = new class implements JsonSerializable {
            /** @return array<string, string> */
            public function jsonSerialize(): array
            {
                return ['delegated' => 'yes'];
            }
        };

        $payload = new ActionPayload(200, $data);

        $this->assertSame(['delegated' => 'yes'], $payload->jsonSerialize());
    }
}
