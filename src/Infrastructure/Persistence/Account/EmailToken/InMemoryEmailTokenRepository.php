<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\EmailToken;

use App\Domain\Account\EmailToken\EmailToken;
use App\Domain\Account\EmailToken\EmailTokenNotFoundException;
use App\Domain\Account\EmailToken\EmailTokenRepository;

class InMemoryEmailTokenRepository implements EmailTokenRepository
{
    /**
     * Two-level map: purpose => did => EmailToken.
     * (Only one active token per purpose+did at a time, matching reference behavior.)
     *
     * @var array<string, array<string, EmailToken>>
     */
    private array $tokens = [];

    /**
     * @param EmailToken[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $token) {
            $this->tokens[$token->getPurpose()][$token->getDid()] = $token;
        }
    }

    public function findByPurposeAndDid(string $purpose, string $did): EmailToken
    {
        if (!isset($this->tokens[$purpose][$did])) {
            throw new EmailTokenNotFoundException();
        }

        return $this->tokens[$purpose][$did];
    }

    public function findByPurposeAndToken(string $purpose, string $token): EmailToken
    {
        foreach ($this->tokens[$purpose] ?? [] as $entry) {
            if ($entry->getToken() === $token) {
                return $entry;
            }
        }

        throw new EmailTokenNotFoundException();
    }

    public function save(EmailToken $token): void
    {
        $this->tokens[$token->getPurpose()][$token->getDid()] = $token;
    }

    public function deleteByPurposeAndDid(string $purpose, string $did): void
    {
        unset($this->tokens[$purpose][$did]);
    }
}
