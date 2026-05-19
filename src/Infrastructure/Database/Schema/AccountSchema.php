<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Schema;

use App\Infrastructure\Database\Database;

/**
 * Initialises the schema for the service-level "account" database, which
 * holds account-related and OAuth tables.
 */
final class AccountSchema
{
    public static function apply(Database $db): void
    {
        $pdo = $db->pdo();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account (
                did                TEXT PRIMARY KEY,
                email              TEXT NOT NULL UNIQUE,
                password_scrypt    TEXT NOT NULL,
                email_confirmed_at TEXT,
                invites_disabled   INTEGER NOT NULL DEFAULT 0
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS actor (
                did             TEXT PRIMARY KEY,
                handle          TEXT UNIQUE,
                created_at      TEXT NOT NULL,
                takedown_ref    TEXT,
                deactivated_at  TEXT,
                delete_after    TEXT
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_password (
                did             TEXT NOT NULL,
                name            TEXT NOT NULL,
                password_scrypt TEXT NOT NULL,
                created_at      TEXT NOT NULL,
                privileged      INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (did, name)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_token (
                purpose      TEXT NOT NULL,
                did          TEXT NOT NULL,
                token        TEXT NOT NULL,
                requested_at TEXT NOT NULL,
                PRIMARY KEY (purpose, did),
                UNIQUE (purpose, token)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS invite_code (
                code           TEXT PRIMARY KEY,
                available_uses INTEGER NOT NULL,
                disabled       INTEGER NOT NULL DEFAULT 0,
                for_account    TEXT NOT NULL,
                created_by     TEXT NOT NULL,
                created_at     TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS invite_code_use (
                code    TEXT NOT NULL,
                used_by TEXT NOT NULL,
                used_at TEXT NOT NULL,
                PRIMARY KEY (code, used_by, used_at)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS refresh_token (
                id                TEXT PRIMARY KEY,
                did               TEXT NOT NULL,
                expires_at        TEXT NOT NULL,
                app_password_name TEXT,
                next_id           TEXT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS refresh_token_did_idx ON refresh_token (did)');

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS account_device (
                did        TEXT NOT NULL,
                device_id  TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                PRIMARY KEY (did, device_id)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS authorized_client (
                did        TEXT NOT NULL,
                client_id  TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                data_json  TEXT NOT NULL,
                PRIMARY KEY (did, client_id)
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS device (
                id           TEXT PRIMARY KEY,
                session_id   TEXT NOT NULL,
                user_agent   TEXT,
                ip_address   TEXT NOT NULL,
                last_seen_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS oauth_token (
                id                    INTEGER PRIMARY KEY AUTOINCREMENT,
                did                   TEXT NOT NULL,
                token_id              TEXT NOT NULL UNIQUE,
                created_at            TEXT NOT NULL,
                updated_at            TEXT NOT NULL,
                expires_at            TEXT NOT NULL,
                client_id             TEXT NOT NULL,
                client_auth_json      TEXT NOT NULL,
                device_id             TEXT,
                parameters_json       TEXT NOT NULL,
                details_json          TEXT,
                code                  TEXT,
                current_refresh_token TEXT,
                scope                 TEXT
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS oauth_token_did_idx  ON oauth_token (did)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS oauth_token_code_idx ON oauth_token (code)');
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS oauth_token_refresh_idx ON oauth_token (current_refresh_token)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS used_refresh_token (
                refresh_token TEXT PRIMARY KEY,
                token_id      INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS used_refresh_token_token_id_idx ON used_refresh_token (token_id)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS authorization_request (
                id                TEXT PRIMARY KEY,
                did               TEXT,
                device_id         TEXT,
                client_id         TEXT NOT NULL,
                client_auth_json  TEXT,
                parameters_json   TEXT NOT NULL,
                expires_at        TEXT NOT NULL,
                code              TEXT
            )'
        );
        $pdo->exec(
            'CREATE INDEX IF NOT EXISTS authorization_request_code_idx ON authorization_request (code)'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS lexicon (
                nsid              TEXT PRIMARY KEY,
                created_at        TEXT NOT NULL,
                updated_at        TEXT NOT NULL,
                last_succeeded_at TEXT,
                uri               TEXT,
                lexicon_json      TEXT
            )'
        );
    }
}
