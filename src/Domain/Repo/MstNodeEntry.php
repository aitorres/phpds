<?php

declare(strict_types=1);

namespace App\Domain\Repo;

use InvalidArgumentException;

final class MstNodeEntry
{
    public function __construct(
        private readonly int $prefixLength,
        private readonly string $keySuffix,
        private readonly CidLink $value,
        private readonly ?CidLink $right,
    ) {
        if ($this->prefixLength < 0) {
            throw new InvalidArgumentException('MST node entry prefix length must be greater than or equal to zero.');
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function fromMap(array $entry): self
    {
        $prefixLength = $entry['p'] ?? null;
        $keySuffix = $entry['k'] ?? null;
        $value = $entry['v'] ?? null;
        $right = $entry['t'] ?? null;

        if (!is_int($prefixLength)) {
            throw new InvalidArgumentException('MST node entry prefix length must be an integer.');
        }

        if (!$keySuffix instanceof CborBytes) {
            throw new InvalidArgumentException('MST node entry key suffix must be a CBOR byte string.');
        }

        if (!$value instanceof CidLink) {
            throw new InvalidArgumentException('MST node entry value must be a CID link.');
        }

        if ($right !== null && !$right instanceof CidLink) {
            throw new InvalidArgumentException('MST node entry right child must be a CID link or null.');
        }

        return new self(
            prefixLength: $prefixLength,
            keySuffix: $keySuffix->getBytes(),
            value: $value,
            right: $right,
        );
    }

    public function getPrefixLength(): int
    {
        return $this->prefixLength;
    }

    public function getKeySuffix(): string
    {
        return $this->keySuffix;
    }

    public function getValue(): CidLink
    {
        return $this->value;
    }

    public function getRight(): ?CidLink
    {
        return $this->right;
    }

    public function expandKey(string $previousKey): string
    {
        return substr($previousKey, 0, $this->prefixLength) . $this->keySuffix;
    }

    /**
     * @return array<string, mixed>
     */
    public function toMap(): array
    {
        return [
            'p' => $this->prefixLength,
            'k' => new CborBytes($this->keySuffix),
            'v' => $this->value,
            't' => $this->right,
        ];
    }
}
