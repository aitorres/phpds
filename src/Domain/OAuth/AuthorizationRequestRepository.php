<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface AuthorizationRequestRepository
{
    /**
     * @throws AuthorizationRequestNotFoundException
     */
    public function findById(string $id): AuthorizationRequest;

    /**
     * @throws AuthorizationRequestNotFoundException
     */
    public function findByCode(string $code): AuthorizationRequest;

    public function save(AuthorizationRequest $request): void;

    public function deleteById(string $id): void;

    public function deleteExpired(): int;
}
