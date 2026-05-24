<?php

declare(strict_types=1);

namespace App\Domain\Account\Password;

/**
 * Abstraction over the password hashing scheme used to protect account
 * and app-password secrets at rest.
 *
 * Implementations should produce hashes that embed their own algorithm
 * parameters so {@see verify()} can validate them without out-of-band data.
 */
interface PasswordHasher
{
    /**
     * Compute a deterministic-format hash for the given plaintext password.
     */
    public function hash(string $plaintext): string;

    /**
     * Verify that $plaintext matches a previously produced $hash.
     *
     * Implementations must be constant-time and must return false (not throw)
     * for malformed or unrecognized hashes.
     */
    public function verify(string $plaintext, string $hash): bool;
}
