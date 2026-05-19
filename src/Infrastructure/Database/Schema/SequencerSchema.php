<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Schema;

use App\Infrastructure\Database\Database;

final class SequencerSchema
{
    public static function apply(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS repo_seq (
                seq           INTEGER PRIMARY KEY AUTOINCREMENT,
                did           TEXT NOT NULL,
                event_type    TEXT NOT NULL,
                event         BLOB NOT NULL,
                sequenced_at  TEXT NOT NULL,
                invalidated   INTEGER NOT NULL DEFAULT 0
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS repo_seq_did_idx ON repo_seq (did)');
    }
}
