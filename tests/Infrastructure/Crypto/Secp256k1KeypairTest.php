<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Crypto;

use App\Domain\Crypto\DidKeyEncoder;
use App\Infrastructure\Crypto\Secp256k1KeypairFactory;
use Tests\TestCase;

class Secp256k1KeypairTest extends TestCase
{
    private Secp256k1KeypairFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new Secp256k1KeypairFactory();
    }

    public function testCurveNameIsK256(): void
    {
        $kp = $this->factory->generate();
        $this->assertSame('k256', $kp->getCurveName());
    }

    public function testGetPublicKeyBytesIs33Bytes(): void
    {
        $kp = $this->factory->generate();
        $this->assertSame(33, strlen($kp->getPublicKeyBytes()));
    }

    public function testGetPublicKeyBytesIsCompressedForm(): void
    {
        // Compressed secp256k1 public keys start with 0x02 or 0x03.
        $kp     = $this->factory->generate();
        $prefix = ord($kp->getPublicKeyBytes()[0]);
        $this->assertContains($prefix, [0x02, 0x03]);
    }

    public function testKnownVectorDidKey(): void
    {
        $privHex = '9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $kp      = $this->factory->fromPrivateKeyHex($privHex);

        $this->assertSame(
            'did:key:zQ3shokFTS3brHcDQrn82RUDfCZESWL1ZdCEJwekUDPQiYBme',
            $kp->getDidKey()
        );
    }

    public function testKnownVectorPublicKey(): void
    {
        $privHex        = '9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $expectedPubHex = '03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd';

        $kp = $this->factory->fromPrivateKeyHex($privHex);
        $this->assertSame($expectedPubHex, bin2hex($kp->getPublicKeyBytes()));
    }

    public function testGetDidKeyStartsWithDidKeyPrefix(): void
    {
        $kp = $this->factory->generate();
        $this->assertStringStartsWith(DidKeyEncoder::PREFIX, $kp->getDidKey());
    }

    public function testGetDidKeyIsSecp256k1Type(): void
    {
        // All k256 keys encode to a did:key starting with 'did:key:zQ3s'.
        $kp = $this->factory->generate();
        $this->assertStringStartsWith('did:key:zQ3s', $kp->getDidKey());
    }

    public function testSignAndVerifyRoundtrip(): void
    {
        $kp      = $this->factory->generate();
        $message = 'hello atproto world';
        $sig     = $kp->sign($message);

        $this->assertTrue($kp->verify($message, $sig));
    }

    public function testSignProduces64ByteSignature(): void
    {
        $kp  = $this->factory->generate();
        $sig = $kp->sign('test message');
        $this->assertSame(64, strlen($sig));
    }

    public function testVerifyReturnsFalseForWrongMessage(): void
    {
        $kp  = $this->factory->generate();
        $sig = $kp->sign('correct message');
        $this->assertFalse($kp->verify('wrong message', $sig));
    }

    public function testVerifyReturnsFalseForTamperedSignature(): void
    {
        $kp      = $this->factory->generate();
        $sig     = $kp->sign('message');
        $tampered = str_repeat("\x00", 64);
        $this->assertFalse($kp->verify('message', $tampered));
    }

    public function testVerifyReturnsFalseForWrongLength(): void
    {
        $kp = $this->factory->generate();
        $this->assertFalse($kp->verify('message', 'tooshort'));
    }

    public function testSignatureIsLowS(): void
    {
        // Low-S means s < half curve order n/2.
        // n = FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141
        // n/2 = 7FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF5D576E7357A4501DDFE92F46681B20A0
        $halfN = gmp_init('7FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF5D576E7357A4501DDFE92F46681B20A0', 16);

        for ($i = 0; $i < 5; $i++) {
            $kp  = $this->factory->generate();
            $sig = $kp->sign(random_bytes(32));
            $s   = gmp_import(substr($sig, 32, 32));
            $this->assertLessThanOrEqual(0, gmp_cmp($s, $halfN), 's must be ≤ n/2 (low-S)');
        }
    }

    public function testExportIs32Bytes(): void
    {
        $kp = $this->factory->generate();
        $this->assertSame(32, strlen($kp->export()));
    }

    public function testExportRoundtripViaFromPrivateKeyBytes(): void
    {
        $kp1       = $this->factory->generate();
        $privBytes = $kp1->export();
        $kp2       = $this->factory->fromPrivateKeyBytes($privBytes);

        $this->assertSame($kp1->getPublicKeyBytes(), $kp2->getPublicKeyBytes());
        $this->assertSame($kp1->getDidKey(), $kp2->getDidKey());
    }

    public function testExportRoundtripViaFromPrivateKeyHex(): void
    {
        $privHex = '9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c';
        $kp1     = $this->factory->fromPrivateKeyHex($privHex);
        $kp2     = $this->factory->fromPrivateKeyHex(bin2hex($kp1->export()));

        $this->assertSame($kp1->getPublicKeyBytes(), $kp2->getPublicKeyBytes());
    }
}
