<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface DeviceRepository
{
    /**
     * @throws DeviceNotFoundException
     */
    public function findById(string $id): Device;

    public function save(Device $device): void;

    public function deleteById(string $id): void;
}
