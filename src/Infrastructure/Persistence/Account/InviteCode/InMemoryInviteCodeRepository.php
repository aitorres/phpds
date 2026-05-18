<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;

class InMemoryInviteCodeRepository implements InviteCodeRepository
{
    /** @var array<string, InviteCode> keyed by code */
    private array $codes = [];

    /** @var array<string, InviteCodeUse[]> keyed by code */
    private array $uses = [];

    /**
     * @param InviteCode[]    $seeds
     * @param InviteCodeUse[] $useSeeds
     */
    public function __construct(array $seeds = [], array $useSeeds = [])
    {
        foreach ($seeds as $code) {
            $this->codes[$code->getCode()] = $code;
        }
        foreach ($useSeeds as $use) {
            $this->uses[$use->getCode()][] = $use;
        }
    }

    /**
     * @return InviteCode[]
     */
    public function findAll(): array
    {
        return array_values($this->codes);
    }

    public function findByCode(string $code): InviteCode
    {
        if (!isset($this->codes[$code])) {
            throw new InviteCodeNotFoundException();
        }

        return $this->codes[$code];
    }

    /**
     * @return InviteCode[]
     */
    public function findAllForAccount(string $did): array
    {
        return array_values(
            array_filter(
                $this->codes,
                fn(InviteCode $c) => $c->getForAccount() === $did,
            )
        );
    }

    public function save(InviteCode $inviteCode): void
    {
        $this->codes[$inviteCode->getCode()] = $inviteCode;
    }

    public function disable(string $code): void
    {
        if (!isset($this->codes[$code])) {
            throw new InviteCodeNotFoundException();
        }

        $old = $this->codes[$code];
        $this->codes[$code] = new InviteCode(
            code: $old->getCode(),
            availableUses: $old->getAvailableUses(),
            disabled: true,
            forAccount: $old->getForAccount(),
            createdBy: $old->getCreatedBy(),
            createdAt: $old->getCreatedAt(),
        );
    }

    /**
     * @return InviteCodeUse[]
     */
    public function findUsesForCode(string $code): array
    {
        return $this->uses[$code] ?? [];
    }

    public function recordUse(InviteCodeUse $use): void
    {
        $this->uses[$use->getCode()][] = $use;
    }
}
