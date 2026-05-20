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

    /**
     * @return InviteCode[]
     */
    public function findPageByRecent(?string $cursorCreatedAt, ?string $cursorCode, int $limit): array;

    /**
     * @return InviteCode[]
     */
    public function findPageByUsage(?int $cursorUses, ?string $cursorCode, int $limit): array;

    public function save(InviteCode $inviteCode): void;

    public function disable(string $code): void;

    /**
     * @return InviteCodeUse[]
     */
    public function findUsesForCode(string $code): array;

    /**
     * @param list<string> $codes
     * @return array<string, list<InviteCodeUse>>
     */
    public function findUsesForCodes(array $codes): array;

    public function recordUse(InviteCodeUse $use): void;
}
