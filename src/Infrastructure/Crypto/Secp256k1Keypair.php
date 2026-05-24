<?php

declare(strict_types=1);

namespace App\Infrastructure\Crypto;

use App\Domain\Crypto\DidKeyEncoder;
use App\Domain\Crypto\Keypair;
use Elliptic\EC;
use Elliptic\EC\KeyPair as EcKeyPair;
use InvalidArgumentException;

/**
 * secp256k1 (k256) {@see Keypair} backed by simplito/elliptic-php.
 *
 * Signatures are returned in atproto's required "compact low-S" form:
 * the 32-byte big-endian r and s concatenated (64 bytes total), with
 * s normalised to the lower half of the curve order.
 */
final class Secp256k1Keypair implements Keypair
{
    public const CURVE = 'k256';

    private static ?EC $ec = null;

    public function __construct(private readonly EcKeyPair $key)
    {
    }

    public static function ec(): EC
    {
        if (self::$ec === null) {
            self::$ec = new EC('secp256k1');
        }
        return self::$ec;
    }

    public function sign(string $message): string
    {
        $hash = hash('sha256', $message, true);
        // elliptic-php has no type stubs; $sig is an object with BN properties r and s.
        $sig  = $this->key->sign(bin2hex($hash), ['canonical' => true]);

        /** @phpstan-ignore-next-line */
        $r = self::pad32((string) $sig->r->toString(16));
        /** @phpstan-ignore-next-line */
        $s = self::pad32((string) $sig->s->toString(16));

        $bin = hex2bin($r . $s);
        if ($bin === false) {
            throw new \RuntimeException('Failed to encode signature');
        }
        return $bin;
    }

    public function verify(string $message, string $signature): bool
    {
        if (strlen($signature) !== 64) {
            return false;
        }

        $hash = hash('sha256', $message, true);
        $r    = bin2hex(substr($signature, 0, 32));
        $s    = bin2hex(substr($signature, 32, 32));

        return (bool) $this->key->verify(bin2hex($hash), ['r' => $r, 's' => $s]);
    }

    public function getPublicKeyBytes(): string
    {
        // elliptic-php has no type stubs; getPublic() returns mixed.
        /** @phpstan-ignore-next-line */
        $bin = hex2bin((string) $this->key->getPublic(true, 'hex'));
        if ($bin === false || strlen($bin) !== 33) {
            throw new \RuntimeException('Failed to encode compressed public key');
        }
        return $bin;
    }

    public function export(): string
    {
        // elliptic-php has no type stubs; getPrivate() returns mixed.
        /** @phpstan-ignore-next-line */
        $hex = self::pad32((string) $this->key->getPrivate('hex'));
        $bin = hex2bin($hex);
        if ($bin === false || strlen($bin) !== 32) {
            throw new \RuntimeException('Failed to encode private key');
        }
        return $bin;
    }

    public function getDidKey(): string
    {
        return DidKeyEncoder::encodeSecp256k1($this->getPublicKeyBytes());
    }

    public function getCurveName(): string
    {
        return self::CURVE;
    }

    private static function pad32(string $hex): string
    {
        if (strlen($hex) > 64) {
            throw new InvalidArgumentException('hex too long for 32-byte field');
        }
        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }
}
