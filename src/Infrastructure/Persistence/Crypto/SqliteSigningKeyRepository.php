<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Crypto;

use App\Domain\Crypto\SigningKeyRepository;
use App\Domain\Crypto\StoredSigningKey;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

/**
 * SQLite-backed {@see SigningKeyRepository} on the per-actor `signing_key`
 * table (see {@see \App\Infrastructure\Database\Schema\ActorStoreSchema}).
 */
final class SqliteSigningKeyRepository implements SigningKeyRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function get(): ?StoredSigningKey
    {
        $row = $this->db->fetchOne(
            'SELECT curve, private_key, did_key, created_at FROM signing_key WHERE id = 1'
        );
        if ($row === null) {
            return null;
        }

        return new StoredSigningKey(
            curve: Row::str($row, 'curve'),
            privateKey: Row::str($row, 'private_key'),
            didKey: Row::str($row, 'did_key'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
        );
    }

    public function save(StoredSigningKey $key): void
    {
        $this->db->execute(
            'INSERT OR REPLACE INTO signing_key (id, curve, private_key, did_key, created_at)
             VALUES (1, :curve, :private_key, :did_key, :created_at)',
            [
                ':curve'       => $key->getCurve(),
                ':private_key' => $key->getPrivateKey(),
                ':did_key'     => $key->getDidKey(),
                ':created_at'  => $key->getCreatedAt()->format(DATE_ATOM),
            ],
        );
    }
}
