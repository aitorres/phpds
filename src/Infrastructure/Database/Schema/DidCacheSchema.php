<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Schema;

use App\Infrastructure\Database\Database;

final class DidCacheSchema
{
    public static function apply(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS did_doc (
                did        TEXT PRIMARY KEY,
                doc_json   TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );
    }
}
