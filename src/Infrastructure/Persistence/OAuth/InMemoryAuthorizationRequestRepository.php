<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizationRequest;
use App\Domain\OAuth\AuthorizationRequestNotFoundException;
use App\Domain\OAuth\AuthorizationRequestRepository;

class InMemoryAuthorizationRequestRepository implements AuthorizationRequestRepository
{
    /** @var array<string, AuthorizationRequest> keyed by request id */
    private array $requests = [];

    /**
     * @param AuthorizationRequest[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $req) {
            $this->requests[$req->getId()] = $req;
        }
    }

    public function findById(string $id): AuthorizationRequest
    {
        if (!isset($this->requests[$id])) {
            throw new AuthorizationRequestNotFoundException();
        }

        return $this->requests[$id];
    }

    public function findByCode(string $code): AuthorizationRequest
    {
        foreach ($this->requests as $req) {
            if ($req->getCode() === $code) {
                return $req;
            }
        }

        throw new AuthorizationRequestNotFoundException();
    }

    public function save(AuthorizationRequest $request): void
    {
        $this->requests[$request->getId()] = $request;
    }

    public function deleteById(string $id): void
    {
        unset($this->requests[$id]);
    }

    public function deleteExpired(): int
    {
        $now = new \DateTimeImmutable();
        $count = 0;
        foreach ($this->requests as $id => $req) {
            if ($req->getExpiresAt() < $now) {
                unset($this->requests[$id]);
                $count++;
            }
        }

        return $count;
    }
}
