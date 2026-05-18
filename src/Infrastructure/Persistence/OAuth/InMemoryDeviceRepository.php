<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\Device;
use App\Domain\OAuth\DeviceNotFoundException;
use App\Domain\OAuth\DeviceRepository;

class InMemoryDeviceRepository implements DeviceRepository
{
    /** @var array<string, Device> keyed by device id */
    private array $devices = [];

    /**
     * @param Device[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        foreach ($seeds as $device) {
            $this->devices[$device->getId()] = $device;
        }
    }

    public function findById(string $id): Device
    {
        if (!isset($this->devices[$id])) {
            throw new DeviceNotFoundException();
        }

        return $this->devices[$id];
    }

    public function save(Device $device): void
    {
        $this->devices[$device->getId()] = $device;
    }

    public function deleteById(string $id): void
    {
        unset($this->devices[$id]);
    }
}
