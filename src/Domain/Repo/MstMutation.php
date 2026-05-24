<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use InvalidArgumentException;

final class MstMutation
{
    private const ACTION_PUT = 'put';
    private const ACTION_DELETE = 'delete';

    private function __construct(
        private readonly string $action,
        private readonly string $key,
        private readonly ?CidLink $value,
    ) {
        MstKey::ensureValid($this->key);

        if (!in_array($this->action, [self::ACTION_PUT, self::ACTION_DELETE], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported MST mutation action: %s', $this->action));
        }

        if ($this->action === self::ACTION_PUT && $this->value === null) {
            throw new InvalidArgumentException('MST put mutation requires a value CID.');
        }

        if ($this->action === self::ACTION_DELETE && $this->value !== null) {
            throw new InvalidArgumentException('MST delete mutation must not include a value CID.');
        }
    }

    public static function put(string $key, CidLink $value): self
    {
        return new self(self::ACTION_PUT, $key, $value);
    }

    public static function delete(string $key): self
    {
        return new self(self::ACTION_DELETE, $key, null);
    }

    public function isPut(): bool
    {
        return $this->action === self::ACTION_PUT;
    }

    public function isDelete(): bool
    {
        return $this->action === self::ACTION_DELETE;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): ?CidLink
    {
        return $this->value;
    }
}
