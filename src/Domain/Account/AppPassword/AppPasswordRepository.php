<?php

declare(strict_types=1);

namespace App\Domain\Account\AppPassword;

interface AppPasswordRepository
{
    /**
     * @return AppPassword[]
     */
    public function findAllForDid(string $did): array;

    /**
     * @throws AppPasswordNotFoundException
     */
    public function findByDidAndName(string $did, string $name): AppPassword;

    public function save(AppPassword $appPassword): void;

    public function deleteByDidAndName(string $did, string $name): void;
}
