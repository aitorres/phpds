<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\UsedRefreshToken;
use App\Domain\OAuth\UsedRefreshTokenRepository;
use App\Infrastructure\Database\Database;

class SqliteUsedRefreshTokenRepository implements UsedRefreshTokenRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function exists(string $refreshToken): bool
    {
        $stmt = $this->db->pdo()->prepare(
            'SELECT 1 FROM used_refresh_token WHERE refresh_token = ?'
        );
        $stmt->execute([$refreshToken]);

        return $stmt->fetchColumn() !== false;
    }

    public function save(UsedRefreshToken $usedRefreshToken): void
    {
        $stmt = $this->db->pdo()->prepare(
            'INSERT INTO used_refresh_token (refresh_token, token_id) VALUES (?, ?)
             ON CONFLICT(refresh_token) DO UPDATE SET token_id = excluded.token_id'
        );
        $stmt->execute([
            $usedRefreshToken->getRefreshToken(),
            $usedRefreshToken->getTokenId(),
        ]);
    }

    public function deleteAllForTokenId(int $tokenId): void
    {
        $stmt = $this->db->pdo()->prepare(
            'DELETE FROM used_refresh_token WHERE token_id = ?'
        );
        $stmt->execute([$tokenId]);
    }
}
