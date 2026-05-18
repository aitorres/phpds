<?php

declare(strict_types=1);

namespace App\Domain\Account\EmailToken;

interface EmailTokenRepository
{
    /**
     * @throws EmailTokenNotFoundException
     */
    public function findByPurposeAndDid(string $purpose, string $did): EmailToken;

    /**
     * @throws EmailTokenNotFoundException
     */
    public function findByPurposeAndToken(string $purpose, string $token): EmailToken;

    public function save(EmailToken $token): void;

    public function deleteByPurposeAndDid(string $purpose, string $did): void;
}
