<?php

declare(strict_types=1);

namespace App\Infrastructure\Crypto;

use App\Domain\Crypto\Keypair;
use App\Domain\Crypto\KeypairFactory;
use InvalidArgumentException;

final class Secp256k1KeypairFactory implements KeypairFactory
{
    public function generate(): Keypair
    {
        $hex = bin2hex(random_bytes(32));
        $key = Secp256k1Keypair::ec()->keyFromPrivate($hex, 'hex');
        /** @var \Elliptic\EC\KeyPair $key */
        return new Secp256k1Keypair($key);
    }

    public function fromPrivateKeyBytes(string $privateKey): Keypair
    {
        if (strlen($privateKey) !== 32) {
            throw new InvalidArgumentException('secp256k1 private key must be 32 bytes');
        }
        return $this->fromPrivateKeyHex(bin2hex($privateKey));
    }

    public function fromPrivateKeyHex(string $hex): Keypair
    {
        $hex = trim($hex);
        if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X')) {
            $hex = substr($hex, 2);
        }
        if ($hex === '' || strlen($hex) > 64 || !ctype_xdigit($hex)) {
            throw new InvalidArgumentException('invalid secp256k1 private key hex');
        }
        $key = Secp256k1Keypair::ec()->keyFromPrivate(str_pad($hex, 64, '0', STR_PAD_LEFT), 'hex');
        /** @var \Elliptic\EC\KeyPair $key */
        return new Secp256k1Keypair($key);
    }
}
