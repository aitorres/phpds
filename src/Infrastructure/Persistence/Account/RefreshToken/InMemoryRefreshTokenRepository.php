<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\RefreshToken;

use App\Domain\Account\RefreshToken\RefreshToken;
use App\Domain\Account\RefreshToken\RefreshTokenNotFoundException;
use App\Domain\Account\RefreshToken\RefreshTokenRepository;

class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    /** @var array<string, RefreshToken> keyed by token id */
    private array $tokens = [];

    /**
     * @param RefreshToken[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $token) {
            $this->tokens[$token->getId()] = $token;
        }
    }

    public function findById(string $id): RefreshToken
    {
        if (!isset($this->tokens[$id])) {
            throw new RefreshTokenNotFoundException();
        }

        return $this->tokens[$id];
    }

    /**
     * @return RefreshToken[]
     */
    public function findAllForDid(string $did): array
    {
        return array_values(
            array_filter(
                $this->tokens,
                fn(RefreshToken $t) => $t->getDid() === $did,
            )
        );
    }

    public function save(RefreshToken $token): void
    {
        $this->tokens[$token->getId()] = $token;
    }

    public function deleteById(string $id): void
    {
        unset($this->tokens[$id]);
    }

    public function deleteAllForDid(string $did): void
    {
        $this->tokens = array_filter(
            $this->tokens,
            fn(RefreshToken $t) => $t->getDid() !== $did,
        );
    }
}
