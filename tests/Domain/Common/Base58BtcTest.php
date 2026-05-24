<?php

declare(strict_types=1);

namespace Tests\Domain\Common;

use App\Domain\Common\Base58Btc;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests the  Base58 (Base58Check) codec.
 */
class Base58BtcTest extends TestCase
{
    public function testAlphabetIs58Characters(): void
    {
        $this->assertSame(58, strlen(Base58Btc::ALPHABET));
    }

    public function testAlphabetExcludesAmbiguousCharacters(): void
    {
        // '0', 'O', 'I', 'l' must be absent (they look alike).
        foreach (['0', 'O', 'I', 'l'] as $ch) {
            $this->assertFalse(
                str_contains(Base58Btc::ALPHABET, $ch),
                "Ambiguous character '{$ch}' must not appear in the Base58 alphabet"
            );
        }
    }

    public function testEncodeEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', Base58Btc::encode(''));
    }

    public function testDecodeEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', Base58Btc::decode(''));
    }

    public function testEncodeLeadingZeroByteBecomesLeadingOne(): void
    {
        // Each leading 0x00 byte encodes as the first alphabet character '1'.
        $this->assertSame('1', Base58Btc::encode("\x00"));
        $this->assertSame('111', Base58Btc::encode("\x00\x00\x00"));
    }

    public function testDecodeLeadingOneBecomesLeadingZeroByte(): void
    {
        $this->assertSame("\x00", Base58Btc::decode('1'));
        $this->assertSame("\x00\x00\x00", Base58Btc::decode('111'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function knownVectorsProvider(): array
    {
        return [
            // btc address encode/decode sanity vectors (hex → base58).
            'single byte 0x00' => ["\x00",           '1'],
            'single byte 0x39' => ["\x39",           'z'],
            'hello world'      => ['Hello World!',   '2NEpo7TZRRrLZSi2U'],
            'the quick brown'  => [
                'The quick brown fox jumps over the lazy dog.',
                'USm3fpXnKG5EUBx2ndxBDMPVciP5hGey2Jh4NDv6gmeo1LkMeiKrLJUUBk6Z',
            ],
        ];
    }

    /**
     * @dataProvider knownVectorsProvider
     */
    #[DataProvider('knownVectorsProvider')]
    public function testEncodeKnownVectors(string $bytes, string $expected): void
    {
        $this->assertSame($expected, Base58Btc::encode($bytes));
    }

    /**
     * @dataProvider knownVectorsProvider
     */
    #[DataProvider('knownVectorsProvider')]
    public function testDecodeKnownVectors(string $expected, string $encoded): void
    {
        $this->assertSame($expected, Base58Btc::decode($encoded));
    }

    public function testRoundtripPreservesBinaryBytes(): void
    {
        $bytes = random_bytes(64);
        $this->assertSame($bytes, Base58Btc::decode(Base58Btc::encode($bytes)));
    }

    public function testRoundtripPreservesLeadingZeroBytes(): void
    {
        $bytes = "\x00\x00" . random_bytes(16);
        $this->assertSame($bytes, Base58Btc::decode(Base58Btc::encode($bytes)));
    }

    public function testEncodeUsesOnlyAlphabetCharacters(): void
    {
        $encoded = Base58Btc::encode(random_bytes(64));
        $this->assertSame(strlen($encoded), strspn($encoded, Base58Btc::ALPHABET));
    }

    public function testDecodeRejectsInvalidCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid base58 character: '0'");
        Base58Btc::decode('1230abc'); // '0' is not in the btc alphabet
    }
}
