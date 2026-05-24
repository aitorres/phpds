<?php

declare(strict_types=1);

namespace App\Domain\Repo;

final class MstLeaf
{
    public function __construct(
        private readonly string $key,
        private readonly CidLink $value,
        private readonly ?CidLink $right,
    ) {
        MstKey::ensureValid($this->key);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): CidLink
    {
        return $this->value;
    }

    public function getRight(): ?CidLink
    {
        return $this->right;
    }
}
