<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Crypto\Keypair;

final class RecordingKeypair implements Keypair
{
    private ?string $lastSignedMessage = null;

    public function __construct(
        private readonly string $signature,
    ) {
    }

    public function sign(string $message): string
    {
        $this->lastSignedMessage = $message;

        return $this->signature;
    }

    public function verify(string $message, string $signature): bool
    {
        return $message === $this->lastSignedMessage && $signature === $this->signature;
    }

    public function getPublicKeyBytes(): string
    {
        return str_repeat("\x01", 33);
    }

    public function export(): string
    {
        return str_repeat("\x02", 32);
    }

    public function getDidKey(): string
    {
        return 'did:key:test';
    }

    public function getCurveName(): string
    {
        return 'k256';
    }

    public function getLastSignedMessage(): ?string
    {
        return $this->lastSignedMessage;
    }
}
