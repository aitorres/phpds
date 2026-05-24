<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Crypto;

use App\Infrastructure\Crypto\Secp256k1KeypairFactory;
use InvalidArgumentException;
use Tests\TestCase;

class Secp256k1KeypairFactoryTest extends TestCase
{
    private Secp256k1KeypairFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Secp256k1KeypairFactory();
    }

    public function testGenerateReturnsKeypair(): void
    {
        $kp = $this->factory->generate();
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testGenerateProducesDifferentKeysEachCall(): void
    {
        $kp1 = $this->factory->generate();
        $kp2 = $this->factory->generate();
        $this->assertNotSame($kp1->getPublicKeyBytes(), $kp2->getPublicKeyBytes());
    }

    public function testFromPrivateKeyHexAcceptsLowercaseHex(): void
    {
        $hex = '9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $kp  = $this->factory->fromPrivateKeyHex($hex);
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyHexAccepts0xPrefix(): void
    {
        $hex = '0x9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $kp  = $this->factory->fromPrivateKeyHex($hex);
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyHexAccepts0XUppercasePrefix(): void
    {
        $hex = '0X9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $kp  = $this->factory->fromPrivateKeyHex($hex);
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyHexTrimsWhitespace(): void
    {
        $hex = '  9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c  ';
        $kp  = $this->factory->fromPrivateKeyHex($hex);
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyHexAcceptsShortHexWithLeftPadding(): void
    {
        // A hex string shorter than 64 chars should be left-padded with zeros.
        $kp = $this->factory->fromPrivateKeyHex('01');
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyHexRejectsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->fromPrivateKeyHex('');
    }

    public function testFromPrivateKeyHexRejectsNonHexString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->fromPrivateKeyHex('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz');
    }

    public function testFromPrivateKeyHexRejectsTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->fromPrivateKeyHex(str_repeat('a', 65)); // 65 hex chars > 64
    }

    public function testFromPrivateKeyBytesAccepts32Bytes(): void
    {
        $kp = $this->factory->fromPrivateKeyBytes(random_bytes(32));
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testFromPrivateKeyBytesRejectsNot32Bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->fromPrivateKeyBytes(random_bytes(31));
    }

    public function testFromPrivateKeyBytesRejectsTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->factory->fromPrivateKeyBytes(random_bytes(33));
    }

    public function testFromPrivateKeyBytesMatchesFromPrivateKeyHex(): void
    {
        $hex   = '9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $bytes = hex2bin($hex);
        assert($bytes !== false);

        $kpHex   = $this->factory->fromPrivateKeyHex($hex);
        $kpBytes = $this->factory->fromPrivateKeyBytes($bytes);

        $this->assertSame($kpHex->getPublicKeyBytes(), $kpBytes->getPublicKeyBytes());
        $this->assertSame($kpHex->getDidKey(), $kpBytes->getDidKey());
    }
}
