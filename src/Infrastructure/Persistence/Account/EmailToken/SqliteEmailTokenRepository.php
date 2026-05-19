<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Account\EmailToken;

use App\Domain\Account\EmailToken\EmailToken;
use App\Domain\Account\EmailToken\EmailTokenNotFoundException;
use App\Domain\Account\EmailToken\EmailTokenRepository;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Row;
use DateTimeImmutable;

class SqliteEmailTokenRepository implements EmailTokenRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    public function findByPurposeAndDid(string $purpose, string $did): EmailToken
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM email_token WHERE purpose = ? AND did = ?',
            [$purpose, $did]
        );

        if ($row === null) {
            throw new EmailTokenNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function findByPurposeAndToken(string $purpose, string $token): EmailToken
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM email_token WHERE purpose = ? AND token = ?',
            [$purpose, $token]
        );

        if ($row === null) {
            throw new EmailTokenNotFoundException();
        }

        return $this->hydrate($row);
    }

    public function save(EmailToken $token): void
    {
        $this->db->execute(
            'INSERT INTO email_token (purpose, did, token, requested_at)
             VALUES (:purpose, :did, :token, :requested_at)
             ON CONFLICT(purpose, did) DO UPDATE SET
                token = excluded.token,
                requested_at = excluded.requested_at',
            [
                'purpose'      => $token->getPurpose(),
                'did'          => $token->getDid(),
                'token'        => $token->getToken(),
                'requested_at' => $token->getRequestedAt()->format(DATE_ATOM),
            ]
        );
    }

    public function deleteByPurposeAndDid(string $purpose, string $did): void
    {
        $this->db->execute(
            'DELETE FROM email_token WHERE purpose = ? AND did = ?',
            [$purpose, $did]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): EmailToken
    {
        return new EmailToken(
            purpose: Row::str($row, 'purpose'),
            did: Row::str($row, 'did'),
            token: Row::str($row, 'token'),
            requestedAt: new DateTimeImmutable(Row::str($row, 'requested_at')),
        );
    }
}
