<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

/**
 * Persistence for the per-actor signing keypair.
 *
 * Each ActorStore has exactly one signing key (the secp256k1 key that
 * signs the actor's repo commits).
 */
interface SigningKeyRepository
{
    public function get(): ?StoredSigningKey;

    public function save(StoredSigningKey $key): void;
}
