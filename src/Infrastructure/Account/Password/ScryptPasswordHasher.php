<?php

declare(strict_types=1);

namespace App\Infrastructure\Account\Password;

use App\Domain\Account\Password\PasswordHasher;
use SodiumException;

/**
 * libsodium-backed password hasher using scrypt (salsa208/sha256).
 *
 * Produces self-describing hash strings of the form
 * `$7$<params>$<salt>$<hash>` that embed their own cost parameters,
 * so verification doesn't require any out-of-band state.
 */
final class ScryptPasswordHasher implements PasswordHasher
{
    public function hash(string $plaintext): string
    {
        return sodium_crypto_pwhash_scryptsalsa208sha256_str(
            $plaintext,
            SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE
        );
    }

    public function verify(string $plaintext, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        try {
            return @sodium_crypto_pwhash_scryptsalsa208sha256_str_verify($hash, $plaintext);
        } catch (SodiumException) {
            return false;
        }
    }
}
