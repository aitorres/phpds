<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AccountDevice;
use App\Domain\OAuth\AccountDeviceNotFoundException;
use App\Domain\OAuth\AccountDeviceRepository;

class InMemoryAccountDeviceRepository implements AccountDeviceRepository
{
    /**
     * Flat list; composite key (did, deviceId).
     *
     * @var AccountDevice[]
     */
    private array $entries = [];

    /**
     * @param AccountDevice[] $seeds
     */
    public function __construct(array $seeds = [])
    {
        $this->entries = $seeds;
    }

    public function findByDidAndDeviceId(string $did, string $deviceId): AccountDevice
    {
        foreach ($this->entries as $entry) {
            if ($entry->getDid() === $did && $entry->getDeviceId() === $deviceId) {
                return $entry;
            }
        }

        throw new AccountDeviceNotFoundException();
    }

    /**
     * @return AccountDevice[]
     */
    public function findAllForDid(string $did): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(AccountDevice $e) => $e->getDid() === $did,
            )
        );
    }

    public function save(AccountDevice $accountDevice): void
    {
        foreach ($this->entries as $i => $existing) {
            if (
                $existing->getDid() === $accountDevice->getDid()
                && $existing->getDeviceId() === $accountDevice->getDeviceId()
            ) {
                $this->entries[$i] = $accountDevice;
                return;
            }
        }
        $this->entries[] = $accountDevice;
    }

    public function deleteByDidAndDeviceId(string $did, string $deviceId): void
    {
        $this->entries = array_values(
            array_filter(
                $this->entries,
                fn(AccountDevice $e) => !($e->getDid() === $did && $e->getDeviceId() === $deviceId),
            )
        );
    }
}
