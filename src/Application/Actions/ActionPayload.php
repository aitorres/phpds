<?php

declare(strict_types=1);

namespace App\Application\Actions;

use JsonSerializable;

class ActionPayload implements JsonSerializable
{
    private int $statusCode;

    /**
     * @var array<string, mixed>|object|null
     */
    private $data;

    private ?ActionError $error;

    /**
     * @param array<string, mixed>|object|null $data
     */
    public function __construct(
        int $statusCode = 200,
        $data = null,
        ?ActionError $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null|object
     */
    public function getData()
    {
        return $this->data;
    }

    public function getError(): ?ActionError
    {
        return $this->error;
    }

    /**
     * @return JsonSerializable|array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): JsonSerializable|array
    {
        if ($this->data instanceof JsonSerializable) {
            /** @var JsonSerializable|array<string, mixed> $serialized */
            $serialized = $this->data->jsonSerialize();
            return $serialized;
        }

        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        }

        if ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        /** @var array<string, mixed> */
        return $payload;
    }
}
