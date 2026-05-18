<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\AppPassword;

use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordNotFoundException;
use App\Domain\Account\AppPassword\AppPasswordRepository;

class InMemoryAppPasswordRepository implements AppPasswordRepository
{
    /**
     * did => name => AppPassword.
     *
     * @var array<string, array<string, AppPassword>>
     */
    private array $passwords = [];

    /**
     * @param AppPassword[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $p) {
            $this->passwords[$p->getDid()][$p->getName()] = $p;
        }
    }

    /**
     * @return AppPassword[]
     */
    public function findAllForDid(string $did): array
    {
        return array_values($this->passwords[$did] ?? []);
    }

    public function findByDidAndName(string $did, string $name): AppPassword
    {
        if (!isset($this->passwords[$did][$name])) {
            throw new AppPasswordNotFoundException();
        }

        return $this->passwords[$did][$name];
    }

    public function save(AppPassword $appPassword): void
    {
        $this->passwords[$appPassword->getDid()][$appPassword->getName()] = $appPassword;
    }

    public function deleteByDidAndName(string $did, string $name): void
    {
        unset($this->passwords[$did][$name]);
    }
}
