<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface AccountDeviceRepository
{
    /**
     * @throws AccountDeviceNotFoundException
     */
    public function findByDidAndDeviceId(string $did, string $deviceId): AccountDevice;

    /**
     * @return AccountDevice[]
     */
    public function findAllForDid(string $did): array;

    public function save(AccountDevice $accountDevice): void;

    public function deleteByDidAndDeviceId(string $did, string $deviceId): void;
}
