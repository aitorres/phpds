<?php

declare(strict_types=1);

namespace App\Domain\Crypto;

use App\Domain\Common\Base58Btc;
use InvalidArgumentException;

/**
 * Encode/decode `did:key:z...` multibase strings.
 *
 * For atproto, only secp256k1 (k256) and P-256 (p256) are supported.
 * The format is:
 *
 *   did:key:z<base58btc(varint(multicodec) || compressed_pubkey)>
 *
 * Multicodec codes:
 *   - secp256k1-pub: 0xe7 (varint: 0xe7 0x01)
 *   - p256-pub:      0x1200 (varint: 0x80 0x24)
 */
final class DidKeyEncoder
{
    public const SECP256K1_MULTICODEC = "\xe7\x01";
    public const P256_MULTICODEC      = "\x80\x24";

    public const PREFIX = 'did:key:z';

    private function __construct()
    {
    }

    /**
     * Encode a compressed secp256k1 public key (33 bytes) as a `did:key:z...`.
     */
    public static function encodeSecp256k1(string $compressedPublicKey): string
    {
        if (strlen($compressedPublicKey) !== 33) {
            throw new InvalidArgumentException('secp256k1 compressed public key must be 33 bytes');
        }

        return self::PREFIX . Base58Btc::encode(self::SECP256K1_MULTICODEC . $compressedPublicKey);
    }

    /**
     * Decode a `did:key:z...` returning [multicodecPrefix, publicKeyBytes].
     *
     * @return array{0: string, 1: string}
     */
    public static function decode(string $didKey): array
    {
        if (!str_starts_with($didKey, self::PREFIX)) {
            throw new InvalidArgumentException("Invalid did:key string: '{$didKey}'");
        }

        $bytes = Base58Btc::decode(substr($didKey, strlen(self::PREFIX)));
        if (strlen($bytes) < 3) {
            throw new InvalidArgumentException("Invalid did:key payload: '{$didKey}'");
        }

        $prefix = substr($bytes, 0, 2);
        $pub    = substr($bytes, 2);

        return [$prefix, $pub];
    }
}
