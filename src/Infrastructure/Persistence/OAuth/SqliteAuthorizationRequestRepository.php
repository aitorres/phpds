<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizationRequest;
use App\Domain\OAuth\AuthorizationRequestNotFoundException;
use App\Domain\OAuth\AuthorizationRequestRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteAuthorizationRequestRepository implements AuthorizationRequestRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findById(string $id): AuthorizationRequest
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM authorization_request WHERE id = ?',
            [$id]
        );

        if ($row === null) {
            throw new AuthorizationRequestNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findByCode(string $code): AuthorizationRequest
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM authorization_request WHERE code = ?',
            [$code]
        );

        if ($row === null) {
            throw new AuthorizationRequestNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(AuthorizationRequest $request): void
    {
        $clientAuth = $request->getClientAuth();
        $clientAuthJson = $clientAuth === null
            ? null
            : json_encode($clientAuth, JSON_THROW_ON_ERROR);

        $this->db->execute(
            'INSERT INTO authorization_request
                (id, did, device_id, client_id, client_auth_json, parameters_json, expires_at, code)
             VALUES
                (:id, :did, :device_id, :client_id, :client_auth_json, :parameters_json, :expires_at, :code)
             ON CONFLICT(id) DO UPDATE SET
                did = excluded.did,
                device_id = excluded.device_id,
                client_id = excluded.client_id,
                client_auth_json = excluded.client_auth_json,
                parameters_json = excluded.parameters_json,
                expires_at = excluded.expires_at,
                code = excluded.code',
            [
                'id'               => $request->getId(),
                'did'              => $request->getDid(),
                'device_id'        => $request->getDeviceId(),
                'client_id'        => $request->getClientId(),
                'client_auth_json' => $clientAuthJson,
                'parameters_json'  => json_encode($request->getParameters(), JSON_THROW_ON_ERROR),
                'expires_at'       => $request->getExpiresAt()->format(DATE_ATOM),
                'code'             => $request->getCode(),
            ]
        );
    }

    public function deleteById(string $id): void
    {
        $this->db->execute('DELETE FROM authorization_request WHERE id = ?', [$id]);
    }

    public function deleteExpired(): int
    {
        return $this->db->execute(
            'DELETE FROM authorization_request WHERE expires_at < ?',
            [(new DateTimeImmutable())->format(DATE_ATOM)]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuthorizationRequest
    {
        $clientAuthJson = Row::nstr($row, 'client_auth_json');
        $clientAuth = $clientAuthJson === null
            ? null
            : json_decode($clientAuthJson, true, 512, JSON_THROW_ON_ERROR);
        $parameters = json_decode(Row::str($row, 'parameters_json'), true, 512, JSON_THROW_ON_ERROR);

        assert(is_array($parameters));
        assert($clientAuth === null || is_array($clientAuth));
        /** @var array<string, mixed> $parameters */
        /** @var array<string, mixed>|null $clientAuth */

        return new AuthorizationRequest(
            id: Row::str($row, 'id'),
            did: Row::nstr($row, 'did'),
            deviceId: Row::nstr($row, 'device_id'),
            clientId: Row::str($row, 'client_id'),
            clientAuth: $clientAuth,
            parameters: $parameters,
            expiresAt: new DateTimeImmutable(Row::str($row, 'expires_at')),
            code: Row::nstr($row, 'code'),
        );
    }
}
