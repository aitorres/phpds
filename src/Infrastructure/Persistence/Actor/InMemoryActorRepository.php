<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Actor;

use App\Application\Settings\SettingsInterface;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorNotFoundException;
use App\Domain\Actor\ActorRepository;

class InMemoryActorRepository implements ActorRepository
{
    /**
     * @var array<string, Actor>
     */
    private array $actorsByDid;

    /**
     * @param Actor[]|null $actors
     */
    public function __construct(?SettingsInterface $settings = null, ?array $actors = null)
    {
        if ($actors === null) {
            /** @var array{hostname: string}|null $pdsSettings */
            $pdsSettings = $settings?->get('pds');
            $hostname = $pdsSettings['hostname'] ?? 'localhost';
            $actors = [
                new Actor(
                    "did:web:alice.{$hostname}",
                    "alice.{$hostname}",
                    new \DateTimeImmutable('2024-01-01T00:00:00Z')
                ),
                new Actor(
                    "did:web:bob.{$hostname}",
                    "bob.{$hostname}",
                    new \DateTimeImmutable('2024-01-02T00:00:00Z')
                ),
                new Actor(
                    'did:plc:carol000000000000000000000',
                    "carol.{$hostname}",
                    new \DateTimeImmutable('2024-01-03T00:00:00Z')
                ),
            ];
        }

        $this->actorsByDid = [];
        foreach ($actors as $actor) {
            $this->actorsByDid[$actor->getDid()] = $actor;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(): array
    {
        return array_values($this->actorsByDid);
    }

    /**
     * {@inheritdoc}
     */
    public function findActorByDid(string $did): Actor
    {
        $key = trim($did);

        if (!isset($this->actorsByDid[$key])) {
            throw new ActorNotFoundException();
        }

        return $this->actorsByDid[$key];
    }

    /**
     * {@inheritdoc}
     */
    public function findActorByHandle(string $handle): Actor
    {
        $needle = strtolower(trim($handle));

        if ($needle === '') {
            throw new ActorNotFoundException();
        }

        foreach ($this->actorsByDid as $actor) {
            if ($actor->getHandle() === $needle) {
                return $actor;
            }
        }

        throw new ActorNotFoundException();
    }
}
