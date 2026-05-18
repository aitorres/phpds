<?php

declare(strict_types=1);

namespace App\Domain\Account\InviteCode;

interface InviteCodeRepository
{
    /**
     * @return InviteCode[]
     */
    public function findAll(): array;

    /**
     * @throws InviteCodeNotFoundException
     */
    public function findByCode(string $code): InviteCode;

    /**
     * @return InviteCode[]
     */
    public function findAllForAccount(string $did): array;

    public function save(InviteCode $inviteCode): void;

    public function disable(string $code): void;

    /**
     * @return InviteCodeUse[]
     */
    public function findUsesForCode(string $code): array;

    public function recordUse(InviteCodeUse $use): void;
}
