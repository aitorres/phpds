<?php

declare(strict_types=1);

namespace App\Domain\Account;

use DateTimeImmutable;
use JsonSerializable;

class Account implements JsonSerializable
{
    private string $did;

    private string $email;

    private string $passwordScrypt;

    private ?DateTimeImmutable $emailConfirmedAt;

    private bool $invitesDisabled;

    public function __construct(
        string $did,
        string $email,
        string $passwordScrypt,
        ?DateTimeImmutable $emailConfirmedAt = null,
        bool $invitesDisabled = false
    ) {
        $this->did = $did;
        $this->email = strtolower(trim($email));
        $this->passwordScrypt = $passwordScrypt;
        $this->emailConfirmedAt = $emailConfirmedAt;
        $this->invitesDisabled = $invitesDisabled;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordScrypt(): string
    {
        return $this->passwordScrypt;
    }

    public function getEmailConfirmedAt(): ?DateTimeImmutable
    {
        return $this->emailConfirmedAt;
    }

    public function isInvitesDisabled(): bool
    {
        return $this->invitesDisabled;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return [
            'did' => $this->did,
            'email' => $this->email,
            'emailConfirmedAt' => $this->emailConfirmedAt?->format(DATE_ATOM),
            'invitesDisabled' => $this->invitesDisabled,
        ];
    }
}
