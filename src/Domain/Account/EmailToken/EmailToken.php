<?php

declare(strict_types=1);

namespace App\Domain\Account\EmailToken;

use DateTimeImmutable;
use JsonSerializable;

class EmailToken implements JsonSerializable
{
    /** Valid purpose values matching the reference implementation */
    public const PURPOSE_CONFIRM_EMAIL  = 'confirm_email';
    public const PURPOSE_UPDATE_EMAIL   = 'update_email';
    public const PURPOSE_RESET_PASSWORD = 'reset_password';
    public const PURPOSE_DELETE_ACCOUNT = 'delete_account';
    public const PURPOSE_PLC_OPERATION  = 'plc_operation';

    public function __construct(
        private readonly string $purpose,
        private readonly string $did,
        private readonly string $token,
        private readonly DateTimeImmutable $requestedAt,
    ) {
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'purpose'     => $this->purpose,
            'did'         => $this->did,
            'requestedAt' => $this->requestedAt->format(DATE_ATOM),
        ];
    }
}
