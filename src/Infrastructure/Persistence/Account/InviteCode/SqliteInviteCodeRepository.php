<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\InviteCode;

use App\Domain\Account\InviteCode\InviteCode;
use App\Domain\Account\InviteCode\InviteCodeNotFoundException;
use App\Domain\Account\InviteCode\InviteCodeRepository;
use App\Domain\Account\InviteCode\InviteCodeUse;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteInviteCodeRepository implements InviteCodeRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return InviteCode[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM invite_code ORDER BY code');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrateCode($row);
        }
        return $result;
    }

    public function findByCode(string $code): InviteCode
    {
        $row = $this->db->fetchOne('SELECT * FROM invite_code WHERE code = ?', [$code]);

        if ($row === null) {
            throw new InviteCodeNotFoundException();
        }

        return $this->hydrateCode($row);
    }

    /**
     * @return InviteCode[]
     */
    public function findAllForAccount(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM invite_code WHERE for_account = ? ORDER BY code',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrateCode($row);
        }
        return $result;
    }

    public function save(InviteCode $inviteCode): void
    {
        $this->db->execute(
            'INSERT INTO invite_code
                (code, available_uses, disabled, for_account, created_by, created_at)
             VALUES
                (:code, :available_uses, :disabled, :for_account, :created_by, :created_at)
             ON CONFLICT(code) DO UPDATE SET
                available_uses = excluded.available_uses,
                disabled = excluded.disabled,
                for_account = excluded.for_account,
                created_by = excluded.created_by,
                created_at = excluded.created_at',
            [
                'code'           => $inviteCode->getCode(),
                'available_uses' => $inviteCode->getAvailableUses(),
                'disabled'       => $inviteCode->isDisabled() ? 1 : 0,
                'for_account'    => $inviteCode->getForAccount(),
                'created_by'     => $inviteCode->getCreatedBy(),
                'created_at'     => $inviteCode->getCreatedAt()->format(DATE_ATOM),
            ]
        );
    }

    public function disable(string $code): void
    {
        $exists = $this->db->fetchOne('SELECT 1 FROM invite_code WHERE code = ?', [$code]);
        if ($exists === null) {
            throw new InviteCodeNotFoundException();
        }

        $this->db->execute(
            'UPDATE invite_code SET disabled = 1 WHERE code = ?',
            [$code]
        );
    }

    /**
     * @return InviteCodeUse[]
     */
    public function findUsesForCode(string $code): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM invite_code_use WHERE code = ? ORDER BY used_at',
            [$code]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrateUse($row);
        }
        return $result;
    }

    public function recordUse(InviteCodeUse $use): void
    {
        $this->db->execute(
            'INSERT OR IGNORE INTO invite_code_use (code, used_by, used_at)
             VALUES (?, ?, ?)',
            [
                $use->getCode(),
                $use->getUsedBy(),
                $use->getUsedAt()->format(DATE_ATOM),
            ]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateCode(array $row): InviteCode
    {
        return new InviteCode(
            code: Row::str($row, 'code'),
            availableUses: Row::int($row, 'available_uses'),
            disabled: Row::bool($row, 'disabled'),
            forAccount: Row::str($row, 'for_account'),
            createdBy: Row::str($row, 'created_by'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateUse(array $row): InviteCodeUse
    {
        return new InviteCodeUse(
            code: Row::str($row, 'code'),
            usedBy: Row::str($row, 'used_by'),
            usedAt: new DateTimeImmutable(Row::str($row, 'used_at')),
        );
    }
}
