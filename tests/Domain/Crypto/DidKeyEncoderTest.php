<?php

declare(strict_types=1);

namespace Tests\Domain\Crypto;

use App\Domain\Common\Base58Btc;
use App\Domain\Crypto\DidKeyEncoder;
use InvalidArgumentException;
use Tests\TestCase;

class DidKeyEncoderTest extends TestCase
{
    public function testPrefixConstant(): void
    {
        $this->assertSame('did:key:z', DidKeyEncoder::PREFIX);
    }

    public function testSecp256k1MulticodecBytes(): void
    {
        $this->assertSame("\xe7\x01", DidKeyEncoder::SECP256K1_MULTICODEC);
    }

    public function testP256MulticodecBytes(): void
    {
        $this->assertSame("\x80\x24", DidKeyEncoder::P256_MULTICODEC);
    }

    /**
     * Known vector derived from private key 9085d2bef69286a6cbb51623c8fa258629945cd55ca705cc4e66700396894e0c.
     * Values verified against our own Secp256k1Keypair implementation.
     */
    public function testEncodeSecp256k1KnownVector(): void
    {
        $compressedPubHex = '03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd';
        $compressedPub    = hex2bin($compressedPubHex);
        assert($compressedPub !== false);

        $didKey = DidKeyEncoder::encodeSecp256k1($compressedPub);

        $this->assertSame('did:key:zQ3shokFTS3brHcDQrn82RUDfCZESWL1ZdCEJwekUDPQiYBme', $didKey);
    }

    public function testEncodeSecp256k1StartsWithPrefix(): void
    {
        $compressedPub = hex2bin('03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd');
        assert($compressedPub !== false);
        $this->assertStringStartsWith(DidKeyEncoder::PREFIX, DidKeyEncoder::encodeSecp256k1($compressedPub));
    }

    public function testEncodeSecp256k1AllK256KeysSharePrefix(): void
    {
        // All secp256k1 did:keys start with 'did:key:zQ3s' (multicodec e701 → base58 prefix).
        $compressedPub = hex2bin('03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd');
        assert($compressedPub !== false);
        $this->assertStringStartsWith('did:key:zQ3s', DidKeyEncoder::encodeSecp256k1($compressedPub));
    }

    public function testEncodeSecp256k1RejectsNot33Bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DidKeyEncoder::encodeSecp256k1(str_repeat("\x02", 32)); // 32 bytes, not 33
    }

    public function testEncodeSecp256k1RejectsTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DidKeyEncoder::encodeSecp256k1(str_repeat("\x02", 34)); // 34 bytes
    }

    public function testDecodeRoundtrip(): void
    {
        $compressedPub = hex2bin('03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd');
        assert($compressedPub !== false);

        $encoded               = DidKeyEncoder::encodeSecp256k1($compressedPub);
        [$prefix, $pubKeyBytes] = DidKeyEncoder::decode($encoded);

        $this->assertSame(DidKeyEncoder::SECP256K1_MULTICODEC, $prefix);
        $this->assertSame($compressedPub, $pubKeyBytes);
    }

    public function testDecodeKnownVectorReturnsCorrectPubKey(): void
    {
        [$prefix, $pub] = DidKeyEncoder::decode('did:key:zQ3shokFTS3brHcDQrn82RUDfCZESWL1ZdCEJwekUDPQiYBme');

        $this->assertSame(DidKeyEncoder::SECP256K1_MULTICODEC, $prefix);
        $this->assertSame(
            '03874c15c7fda20e539c6e5ba573c139884c351188799f5458b4b41f7924f235cd',
            bin2hex($pub)
        );
    }

    public function testDecodeRejectsStringWithoutDidKeyPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DidKeyEncoder::decode('not:a:did:key');
    }

    public function testDecodeRejectsTooShortPayload(): void
    {
        // 'z' + base58btc of a 2-byte string → only 2 bytes decoded, which is < 3.
        $short = DidKeyEncoder::PREFIX . Base58Btc::encode("\xe7\x01"); // just the 2-byte prefix
        $this->expectException(InvalidArgumentException::class);
        DidKeyEncoder::decode($short);
    }
}
