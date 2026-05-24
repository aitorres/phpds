<?php

declare(strict_types=1);

namespace App\Domain\Did;

use App\Domain\Crypto\Keypair;

/**
 * Submits PLC operations to the configured PLC directory.
 *
 * Each "operation" is a JSON-like map describing the desired identity
 * state. `formatAndSign` adds the signature; `didForOp` derives the
 * resulting `did:plc:*` from a signed op; `submit` POSTs the signed
 * operation to the directory.
 *
 * @see https://github.com/did-method-plc/did-method-plc
 */
interface PlcDirectoryClient
{
    public const OP_TYPE = 'plc_operation';

    /**
     * Build a signed plcOp for a fresh account.
     *
     * @param list<string> $rotationKeys  did:key strings, in priority order
     * @param string $signingKey  did:key string for atproto signing key
     * @param string $handle  bare handle (e.g. "alice.example.com")
     * @param string $pdsEndpoint  full URL (e.g. "https://example.com")
     *
     * @return array<string, mixed>  the signed operation
     */
    public function buildAndSignGenesisOp(
        array $rotationKeys,
        string $signingKey,
        string $handle,
        string $pdsEndpoint,
        Keypair $signer,
    ): array;

    /**
     * Sign an unsigned plcOp (operation lacking a `sig` field).
     *
     * @param array<string, mixed> $unsignedOp
     * @return array<string, mixed> the operation with a `sig` field appended
     */
    public function signOp(array $unsignedOp, Keypair $signer): array;

    /**
     * Compute the `did:plc:*` derived from a signed (genesis) operation.
     *
     * @param array<string, mixed> $signedOp
     */
    public function didForOp(array $signedOp): string;

    /**
     * POST the signed operation to the PLC directory at the given DID.
     *
     * @param array<string, mixed> $signedOp
     *
     * @throws PlcDirectoryClientException on transport/HTTP failure
     */
    public function submit(string $did, array $signedOp): void;
}
