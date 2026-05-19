<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Preference;

use App\Domain\Preference\AccountPref;
use App\Domain\Preference\AccountPrefNotFoundException;
use App\Domain\Preference\AccountPrefRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;

class SqliteAccountPrefRepository implements AccountPrefRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @return AccountPref[]
     */
    public function findAll(): array
    {
        $rows = $this->db->fetchAll('SELECT * FROM account_pref ORDER BY id');

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    /**
     * @return AccountPref[]
     */
    public function findByName(string $name): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM account_pref WHERE name = ? ORDER BY id',
            [$name]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function findById(int $id): AccountPref
    {
        $row = $this->db->fetchOne('SELECT * FROM account_pref WHERE id = ?', [$id]);

        if ($row === null) {
            throw new AccountPrefNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(AccountPref $pref): AccountPref
    {
        if ($pref->getId() === 0) {
            $this->db->execute(
                'INSERT INTO account_pref (name, value_json) VALUES (?, ?)',
                [$pref->getName(), $pref->getValueJson()]
            );

            return new AccountPref(
                id: (int) $this->db->pdo()->lastInsertId(),
                name: $pref->getName(),
                valueJson: $pref->getValueJson(),
            );
        }

        $this->db->execute(
            'INSERT INTO account_pref (id, name, value_json) VALUES (:id, :name, :value_json)
             ON CONFLICT(id) DO UPDATE SET
                name = excluded.name,
                value_json = excluded.value_json',
            [
                'id'         => $pref->getId(),
                'name'       => $pref->getName(),
                'value_json' => $pref->getValueJson(),
            ]
        );

        return $pref;
    }

    public function deleteById(int $id): void
    {
        $this->db->execute('DELETE FROM account_pref WHERE id = ?', [$id]);
    }

    public function deleteByName(string $name): void
    {
        $this->db->execute('DELETE FROM account_pref WHERE name = ?', [$name]);
    }

    public function deleteAll(): void
    {
        $this->db->pdo()->exec('DELETE FROM account_pref');
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AccountPref
    {
        return new AccountPref(
            id: Row::int($row, 'id'),
            name: Row::str($row, 'name'),
            valueJson: Row::str($row, 'value_json'),
        );
    }
}
