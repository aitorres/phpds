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
     * Return a page of actors ordered by DID ascending, starting strictly
     * after $cursor (exclusive). When $cursor is null, starts from the
     * beginning.
     *
     * @return Actor[]
     */
    public function findPage(?string $cursor, int $limit): array;

    /**
     * @throws ActorNotFoundException
     */
    public function findActorByDid(string $did): Actor;

    /**
     * @throws ActorNotFoundException
     */
    public function findActorByHandle(string $handle): Actor;

    /**
     * Persist an actor.
     */
    public function save(Actor $actor): void;
}
