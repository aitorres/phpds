<?php

declare(strict_types=1);

namespace App\Domain\Actor;

interface ActorRepository
{
    /**
     * @return Actor[]
     */
    public function findAll(): array;

    /**
     * @throws ActorNotFoundException
     */
    public function findActorByDid(string $did): Actor;

    /**
     * @throws ActorNotFoundException
     */
    public function findActorByHandle(string $handle): Actor;
}
