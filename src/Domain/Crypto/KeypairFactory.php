<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

interface KeypairFactory
{
    /**
     * Generate a fresh keypair using a cryptographically secure RNG.
     */
    public function generate(): Keypair;

    /**
     * Reconstruct a keypair from previously-exported raw private-key bytes.
     */
    public function fromPrivateKeyBytes(string $privateKey): Keypair;

    /**
     * Load from a hex-encoded private key (no `0x` prefix).
     */
    public function fromPrivateKeyHex(string $hex): Keypair;
}
