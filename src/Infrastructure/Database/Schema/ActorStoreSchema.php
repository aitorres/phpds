<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Schema;

use App\Infrastructure\Database\Database;

/**
 * Initialises the per-actor SQLite database schema.
 * Mirrors the reference TS actor-store layout.
 */
final class ActorStoreSchema
{
    public static function apply(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS record (
                uri          TEXT PRIMARY KEY,
                cid          TEXT NOT NULL,
                collection   TEXT NOT NULL,
                rkey         TEXT NOT NULL,
                repo_rev     TEXT NOT NULL,
                indexed_at   TEXT NOT NULL,
                takedown_ref TEXT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS record_collection_idx ON record (collection)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS record_blob (
                blob_cid   TEXT NOT NULL,
                record_uri TEXT NOT NULL,
                PRIMARY KEY (blob_cid, record_uri)
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS record_blob_record_uri_idx ON record_blob (record_uri)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS backlink (
                uri     TEXT NOT NULL,
                path    TEXT NOT NULL,
                link_to TEXT NOT NULL,
                PRIMARY KEY (uri, path, link_to)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS backlink_link_to_idx ON backlink (link_to)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS blob (
                cid          TEXT PRIMARY KEY,
                mime_type    TEXT NOT NULL,
                size         INTEGER NOT NULL,
                temp_key     TEXT,
                created_at   TEXT NOT NULL,
                takedown_ref TEXT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS blob_temp_key_idx ON blob (temp_key)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS repo_block (
                cid      TEXT PRIMARY KEY,
                repo_rev TEXT NOT NULL,
                size     INTEGER NOT NULL,
                content  BLOB NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS repo_block_repo_rev_idx ON repo_block (repo_rev)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS repo_root (
                did        TEXT PRIMARY KEY,
                cid        TEXT NOT NULL,
                rev        TEXT NOT NULL,
                indexed_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_pref (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT NOT NULL,
                value_json TEXT NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS account_pref_name_idx ON account_pref (name)');
    }
}
