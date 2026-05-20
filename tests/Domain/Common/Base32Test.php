<?php

declare(strict_types=1);

namespace Tests\Domain\Common;

use App\Domain\Common\Base32;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tests RFC 4648 base32 lowercase, no-padding encode/decode.
 */
class Base32Test extends TestCase
{
    public function testAlphabetIsRfc4648LowercaseNoPadding(): void
    {
        $this->assertSame('abcdefghijklmnopqrstuvwxyz234567', Base32::ALPHABET);
        $this->assertSame(32, strlen(Base32::ALPHABET));
    }

    public function testEncodeEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', Base32::encode(''));
    }

    public function testDecodeEmptyStringReturnsEmpty(): void
    {
        $this->assertSame('', Base32::decode(''));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rfc4648VectorsProvider(): array
    {
        // RFC 4648 §10 test vectors, lowercased and stripped of padding.
        return [
            'f'      => ['f',      'my'],
            'fo'     => ['fo',     'mzxq'],
            'foo'    => ['foo',    'mzxw6'],
            'foob'   => ['foob',   'mzxw6yq'],
            'fooba'  => ['fooba',  'mzxw6ytb'],
            'foobar' => ['foobar', 'mzxw6ytboi'],
        ];
    }

    /**
     * @dataProvider rfc4648VectorsProvider
     */
    #[DataProvider('rfc4648VectorsProvider')]
    public function testEncodeMatchesRfc4648Vectors(string $plain, string $encoded): void
    {
        $this->assertSame($encoded, Base32::encode($plain));
    }

    /**
     * @dataProvider rfc4648VectorsProvider
     */
    #[DataProvider('rfc4648VectorsProvider')]
    public function testDecodeMatchesRfc4648Vectors(string $plain, string $encoded): void
    {
        $this->assertSame($plain, Base32::decode($encoded));
    }

    public function testRoundtripPreservesBinaryBytes(): void
    {
        $bytes = random_bytes(64);
        $this->assertSame($bytes, Base32::decode(Base32::encode($bytes)));
    }

    public function testEncodeUsesOnlyAlphabetCharacters(): void
    {
        $encoded = Base32::encode(random_bytes(128));
        $this->assertSame(
            strlen($encoded),
            strspn($encoded, Base32::ALPHABET)
        );
    }

    public function testDecodeRejectsInvalidCharacter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid base32 character: '1'");
        Base32::decode('mzxw1'); // '1' is not in the RFC 4648 alphabet
    }

    public function testDecodeRejectsUppercase(): void
    {
        // The codec is strictly lowercase; uppercase letters must not decode.
        $this->expectException(InvalidArgumentException::class);
        Base32::decode('MZXW6');
    }
}
