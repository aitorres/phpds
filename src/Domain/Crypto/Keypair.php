<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

/**
 * A cryptographic keypair capable of signing bytes and emitting its
 * public-key form as a `did:key:*` string.
 */
interface Keypair
{
    /**
     * Sign the given message bytes with the private key.
     *
     * The signature is returned in low-S compact form (r||s, 64 bytes)
     * as required by atproto.
     */
    public function sign(string $message): string;

    /**
     * Verify a signature against this keypair's public key.
     */
    public function verify(string $message, string $signature): bool;

    /**
     * Return the compressed (33-byte) public key.
     */
    public function getPublicKeyBytes(): string;

    /**
     * Return the raw (32-byte) private key, for persistence.
     */
    public function export(): string;

    /**
     * Return the `did:key:z...` multibase-encoded form of this key's
     * public component.
     */
    public function getDidKey(): string;

    /**
     * Return the multicodec identifier prefix for this key type
     * (e.g. "k256" for secp256k1).
     */
    public function getCurveName(): string;
}
