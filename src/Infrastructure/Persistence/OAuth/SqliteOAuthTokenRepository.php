<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\OAuthToken;
use App\Domain\OAuth\OAuthTokenNotFoundException;
use App\Domain\OAuth\OAuthTokenRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteOAuthTokenRepository implements OAuthTokenRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByTokenId(string $tokenId): OAuthToken
    {
        return $this->fetchOneBy('token_id = ?', [$tokenId]);
    }

    public function findByCode(string $code): OAuthToken
    {
        return $this->fetchOneBy('code = ?', [$code]);
    }

    public function findByRefreshToken(string $refreshToken): OAuthToken
    {
        return $this->fetchOneBy('current_refresh_token = ?', [$refreshToken]);
    }

    /**
     * @return OAuthToken[]
     */
    public function findAllForDid(string $did): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM oauth_token WHERE did = ? ORDER BY id',
            [$did]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($row);
        }
        return $result;
    }

    public function save(OAuthToken $token): void
    {
        $details = $token->getDetails();
        $detailsJson = $details === null
            ? null
            : json_encode($details, JSON_THROW_ON_ERROR);

        $this->db->execute(
            'INSERT INTO oauth_token
                (id, did, token_id, created_at, updated_at, expires_at, client_id,
                 client_auth_json, device_id, parameters_json, details_json, code,
                 current_refresh_token, scope)
             VALUES
                (:id, :did, :token_id, :created_at, :updated_at, :expires_at, :client_id,
                 :client_auth_json, :device_id, :parameters_json, :details_json, :code,
                 :current_refresh_token, :scope)
             ON CONFLICT(token_id) DO UPDATE SET
                did = excluded.did,
                created_at = excluded.created_at,
                updated_at = excluded.updated_at,
                expires_at = excluded.expires_at,
                client_id = excluded.client_id,
                client_auth_json = excluded.client_auth_json,
                device_id = excluded.device_id,
                parameters_json = excluded.parameters_json,
                details_json = excluded.details_json,
                code = excluded.code,
                current_refresh_token = excluded.current_refresh_token,
                scope = excluded.scope',
            [
                'id'                    => $token->getId() === 0 ? null : $token->getId(),
                'did'                   => $token->getDid(),
                'token_id'              => $token->getTokenId(),
                'created_at'            => $token->getCreatedAt()->format(DATE_ATOM),
                'updated_at'            => $token->getUpdatedAt()->format(DATE_ATOM),
                'expires_at'            => $token->getExpiresAt()->format(DATE_ATOM),
                'client_id'             => $token->getClientId(),
                'client_auth_json'      => json_encode($token->getClientAuth(), JSON_THROW_ON_ERROR),
                'device_id'             => $token->getDeviceId(),
                'parameters_json'       => json_encode($token->getParameters(), JSON_THROW_ON_ERROR),
                'details_json'          => $detailsJson,
                'code'                  => $token->getCode(),
                'current_refresh_token' => $token->getCurrentRefreshToken(),
                'scope'                 => $token->getScope(),
            ]
        );
    }

    public function deleteByTokenId(string $tokenId): void
    {
        $this->db->execute('DELETE FROM oauth_token WHERE token_id = ?', [$tokenId]);
    }

    public function deleteAllForDid(string $did): void
    {
        $this->db->execute('DELETE FROM oauth_token WHERE did = ?', [$did]);
    }

    /**
     * @param array<string|int, scalar|null> $params
     */
    private function fetchOneBy(string $where, array $params): OAuthToken
    {
        $row = $this->db->fetchOne("SELECT * FROM oauth_token WHERE $where", $params);

        if ($row === null) {
            throw new OAuthTokenNotFoundException();
        }

        return $this->hydrate($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OAuthToken
    {
        $clientAuth = json_decode(Row::str($row, 'client_auth_json'), true, 512, JSON_THROW_ON_ERROR);
        $parameters = json_decode(Row::str($row, 'parameters_json'), true, 512, JSON_THROW_ON_ERROR);
        $detailsJson = Row::nstr($row, 'details_json');
        $details = $detailsJson === null
            ? null
            : json_decode($detailsJson, true, 512, JSON_THROW_ON_ERROR);

        assert(is_array($clientAuth));
        assert(is_array($parameters));
        assert($details === null || is_array($details));
        /** @var array<string, mixed> $clientAuth */
        /** @var array<string, mixed> $parameters */
        /** @var array<string, mixed>|null $details */

        return new OAuthToken(
            id: Row::int($row, 'id'),
            did: Row::str($row, 'did'),
            tokenId: Row::str($row, 'token_id'),
            createdAt: new DateTimeImmutable(Row::str($row, 'created_at')),
            updatedAt: new DateTimeImmutable(Row::str($row, 'updated_at')),
            expiresAt: new DateTimeImmutable(Row::str($row, 'expires_at')),
            clientId: Row::str($row, 'client_id'),
            clientAuth: $clientAuth,
            deviceId: Row::nstr($row, 'device_id'),
            parameters: $parameters,
            details: $details,
            code: Row::nstr($row, 'code'),
            currentRefreshToken: Row::nstr($row, 'current_refresh_token'),
            scope: Row::nstr($row, 'scope'),
        );
    }
}
