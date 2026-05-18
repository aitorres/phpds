<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Repo;

use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Repo\CidUtil;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Ports test vectors and behavioural assertions from the atproto reference
 * implementation (packages/lex/lex-cbor and packages/lex/lex-data) to verify
 * byte-level and CID interoperability.
 *
 * Sources:
 *   packages/lex/lex-cbor/tests/vectors.ts          — named test vectors
 *   packages/lex/lex-cbor/tests/data-model-fixtures.json — cbor_base64 + cid
 *   packages/lex/lex-cbor/tests/dag-cbor.test.ts    — behavioural assertions
 *   packages/lex/lex-cbor/tests/codec.test.ts       — basic codec assertions
 *   packages/lex/lex-data/src/cid.test.ts           — CID utility assertions
 */
class ReferenceCompatibilityTest extends TestCase
{
    private NativeDagCborEncoder $encoder;
    private NativeDagCborDecoder $decoder;

    protected function setUp(): void
    {
        $this->encoder = new NativeDagCborEncoder();
        $this->decoder = new NativeDagCborDecoder();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // codec.test.ts — basic encode assertions
    // ──────────────────────────────────────────────────────────────────────────

    public function testEncodeHelloWorldProducesExactBytes(): void
    {
        // from codec.test.ts: encode({ hello: 'world' })
        $expected = "\xa1\x65\x68\x65\x6c\x6c\x6f\x65\x77\x6f\x72\x6c\x64";
        $this->assertSame($expected, $this->encoder->encode(['hello' => 'world']));
    }

    public function testDecodeHelloWorldFromExactBytes(): void
    {
        $bytes = "\xa1\x65\x68\x65\x6c\x6c\x6f\x65\x77\x6f\x72\x6c\x64";
        $this->assertEquals(['hello' => 'world'], $this->decoder->decode($bytes));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // codec.test.ts — identity round-trip
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, array<int, mixed>> */
    public static function identityProvider(): array
    {
        $cid1 = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $cid2 = 'bafyreigoxt64qghytzkr6ik7qvtzc7lyytiq5xbbrokbxjows2wp7vmo6q';
        $cid3 = 'bafyreiaizynclnqiolq7byfpjjtgqzn4sfrsgn7z2hhf6bo4utdwkin7ke';
        $cid4 = 'bafyreifd4w4tcr5tluxz7osjtnofffvtsmgdqcfrfi6evjde4pl27lrjpy';

        return [
            'null'            => [null],
            'cid link'        => [new CidLink($cid1)],
            'array of cids'   => [[new CidLink($cid1), new CidLink($cid2), new CidLink($cid3), new CidLink($cid4)]],
            'bytes'           => [new CborBytes('hello world')],
            'true'            => [true],
            'false'           => [false],
            'zero'            => [0],
            'forty-two'       => [42],
            '-1'              => [-1],
            'empty string'    => [''],
            'hello world'     => ['hello world'],
            'empty array'     => [[]],
            'int array'       => [[1, 2, 3]],
            'empty map'       => [['__placeholder__' => 'x']],   // PHP [] is always list
            'simple map'      => [['a' => 1, 'b' => 'two', 'c' => true]],
            'nested'          => [[
                'nested' => [
                    'array'  => ['value' => [1, 2, 3]],
                    'object' => ['key' => 'value'],
                    'cid'    => new CidLink($cid1),
                    'bytes'  => new CborBytes('byte array'),
                ],
            ]],
        ];
    }

    #[DataProvider('identityProvider')]
    public function testIdentityRoundtrip(mixed $value): void
    {
        $cbor    = $this->encoder->encode($value);
        $decoded = $this->decoder->decode($cbor);
        $this->assertEquals($value, $decoded);
        $this->assertEquals($cbor, $this->encoder->encode($decoded));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // codec.test.ts — decodeAll (concatenated dag-cbor messages)
    // ──────────────────────────────────────────────────────────────────────────

    public function testDecodeAllTwoConcatenatedMessages(): void
    {
        $cid  = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $one  = ['a' => 123, 'b' => new CidLink($cid)];
        $two  = ['c' => new CborBytes("\x01\x02\x03"), 'd' => new CidLink($cid)];

        $concatenated = $this->encoder->encode($one) . $this->encoder->encode($two);
        $decoded      = $this->decoder->decodeAll($concatenated);

        $this->assertCount(2, $decoded);
        $this->assertEquals($one, $decoded[0]);
        $this->assertEquals($two, $decoded[1]);
    }

    public function testDecodeAllParsesMaxSafeIntAsInteger(): void
    {
        $encoded = $this->encoder->encode(['test' => PHP_INT_MAX]);
        $decoded = $this->decoder->decodeAll($encoded);

        $this->assertCount(1, $decoded);
        $this->assertIsArray($decoded[0]);
        $this->assertArrayHasKey('test', $decoded[0]);
        $this->assertIsInt($decoded[0]['test']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // dag-cbor.test.ts — IEEE 754 special encoding rejection
    // ──────────────────────────────────────────────────────────────────────────

    public function testEncodeNanThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/NaN.*not supported/');
        $this->encoder->encode(NAN);
    }

    public function testEncodeInfinityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Infinity.*not supported/');
        $this->encoder->encode(INF);
    }

    public function testEncodeNegativeInfinityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/-Infinity.*not supported/');
        $this->encoder->encode(-INF);
    }

    public function testEncodeNanInsideObjectThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->encoder->encode(['a' => 'a', 'b' => NAN]);
    }

    public function testEncodeInfinityInsideArrayThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->encoder->encode([1, -1, INF, -INF]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // dag-cbor.test.ts — IEEE 754 special decoding rejection
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, array{string, string}>
     */
    public static function ieee754DecodeProvider(): array
    {
        return [
            // half-precision NaN variants
            'half NaN f97e00'                    => ['f97e00', 'NaN'],
            'half NaN f97ff8'                    => ['f97ff8', 'NaN'],
            // single-precision NaN
            'single NaN fa7ff80000'              => ['fa7ff80000', 'NaN'],
            // double-precision NaN
            'double NaN fb7ff8000000000000'      => ['fb7ff8000000000000', 'NaN'],
            // NaN in a map value
            'NaN in map'                         => ['a2616161616162fb7ff8000000000000', 'NaN'],
            // half-precision Infinity
            'half +Inf f97c00'                   => ['f97c00', 'Infinity'],
            // double-precision Infinity
            'double +Inf fb7ff0000000000000'     => ['fb7ff0000000000000', 'Infinity'],
            // Infinity in a map value
            '+Inf in map'                        => ['a2616161616162fb7ff0000000000000', 'Infinity'],
            // half-precision -Infinity
            'half -Inf f9fc00'                   => ['f9fc00', '-Infinity'],
            // double-precision -Infinity
            'double -Inf fbfff0000000000000'     => ['fbfff0000000000000', '-Infinity'],
            // -Infinity in a map value
            '-Inf in map'                        => ['a2616161616162fbfff0000000000000', '-Infinity'],
        ];
    }

    #[DataProvider('ieee754DecodeProvider')]
    public function testDecodeIeee754SpecialThrows(string $hex, string $label): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches(
            '/' . preg_quote($label, '/') . '.*not supported/'
        );
        $this->decoder->decode($this->hexBytes($hex));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // dag-cbor.test.ts — structural / protocol errors
    // ──────────────────────────────────────────────────────────────────────────

    public function testDecodeRejectsTrailingData(): void
    {
        // encode a single integer, then append a spurious 0x00 byte
        $cbor = $this->encoder->encode(42);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/too many terminals/');
        $this->decoder->decode($cbor . "\x00");
    }

    public function testDecodeRejectsDuplicateMapKeys(): void
    {
        // a3 636261720363666f6f0163666f6f02 => map(3): "bar"=>3, "foo"=>1, "foo"=>2
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/repeat map key.*foo/');
        $this->decoder->decode($this->hexBytes('a3636261720363666f6f0163666f6f02'));
    }

    public function testDecodeRejectsBadCidLeadIn(): void
    {
        // tag(42) with 0x01 lead-in instead of required 0x00
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid CID for CBOR tag 42.*0x00/');
        $this->decoder->decode(
            $this->hexBytes(
                'a1646c696e6bd82a582501017012207252523e6591fb8fe553d67ff5'
                . '5a86f84044b46a3e4176e10c58fa529a4aabd5'
            )
        );
    }

    public function testDecodeCoercesUndefinedToNull(): void
    {
        // f7 = CBOR undefined -> null
        $this->assertNull($this->decoder->decode("\xf7"));
    }

    public function testDecodeMapWithUndefinedValueCoercesToNull(): void
    {
        // a26362617af763666f6f63626172 = map: "baz"->undefined, "foo"->"bar"
        $decoded = $this->decoder->decode($this->hexBytes('a26362617af763666f6f63626172'));

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('baz', $decoded);
        $this->assertNull($decoded['baz']);
        $this->assertSame('bar', $decoded['foo']);
    }

    public function testSlashPropertyRoundtrip(): void
    {
        // {"/": true} must survive encode -> decode without confusion
        $original = ['/' => true];
        $cbor     = $this->encoder->encode($original);
        $decoded  = $this->decoder->decode($cbor);
        $this->assertEquals($original, $decoded);
    }

    public function testTag42IsPresentForCidLinks(): void
    {
        // d8 2a = CBOR tag 42; each CID link must produce this marker
        $cid  = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $data = [
            'link'  => new CidLink($cid),
            'links' => [new CidLink($cid), new CidLink($cid)],
        ];
        $cbor = $this->encoder->encode($data);

        // 3 CID links -> 3 occurrences of the two-byte tag marker d82a
        $count = substr_count($cbor, "\xd8\x2a");
        $this->assertSame(3, $count);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // vectors.ts + data-model-fixtures.json — byte-exact + CID vectors
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Vector "basic" — primitive types, nested object, no CIDs.
     * cbor_base64 and cid taken from data-model-fixtures.json.
     */
    public function testVectorBasicBytesAndCid(): void
    {
        $input = [
            'string'  => 'abc',
            'unicode' => "a~\xc3\xb6\xc3\xb1\xc2\xa9\xe2\xbd\x98\xe2\x98\x8e"
                . "\xf0\x93\x8b\x93\xf0\x9f\x98\x80\xf0\x9f\x91\xa8"
                . "\xe2\x80\x8d\xf0\x9f\x91\xa9\xe2\x80\x8d\xf0\x9f\x91\xa7"
                . "\xe2\x80\x8d\xf0\x9f\x91\xa7",
            'integer' => 123,
            'bool'    => true,
            'null'    => null,
            'array'   => ['abc', 'def', 'ghi'],
            'object'  => [
                'string' => 'abc',
                'number' => 123,
                'bool'   => true,
                'arr'    => ['abc', 'def', 'ghi'],
            ],
        ];

        $expectedCbor = base64_decode(
            'p2Rib29s9WRudWxs9mVhcnJheYNjYWJjY2RlZmNnaGlmb2JqZWN0pGNhcnKDY2FiY2Nk'
            . 'ZWZjZ2hpZGJvb2z1Zm51bWJlchh7ZnN0cmluZ2NhYmNmc3RyaW5nY2FiY2dpbnRlZ2Vy'
            . 'GHtndW5pY29kZXgvYX7DtsOxwqnivZjimI7wk4uT8J+YgPCfkajigI3wn5Gp4oCN8J+R'
            . 'p+KAjfCfkac'
        );
        $expectedCid  = 'bafyreiclp443lavogvhj3d2ob2cxbfuscni2k5jk7bebjzg7khl3esabwq';

        $cbor = $this->encoder->encode($input);

        $this->assertSame($expectedCbor, $cbor, 'basic vector: CBOR bytes must match reference');
        $this->assertSame($expectedCid, CidUtil::computeForDagCbor($cbor), 'basic vector: CID must match reference');

        // Round-trip
        $decoded = $this->decoder->decode($cbor);
        $this->assertEquals($input, $decoded);
    }

    /**
     * Vector "ipld" — CidLink + CborBytes + nested map.
     * cbor_base64 and cid taken from data-model-fixtures.json.
     */
    public function testVectorIpldBytesAndCid(): void
    {
        $input = [
            'a' => new CidLink('bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a'),
            'b' => new CborBytes(base64_decode('nFERjvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI0')),
            'c' => [
                '$type'    => 'blob',
                'ref'      => new CidLink('bafkreiccldh766hwcnuxnf2wh6jgzepf2nlu2lvcllt63eww5p6chi4ity'),
                'mimeType' => 'image/jpeg',
                'size'     => 10000,
            ],
        ];

        $expectedCbor = base64_decode(
            'o2Fh2CpYJQABcRIgZQYqWloA/BbXPGlEI3zLwVscSnI0SJM2iR0JF0GiOdBhYlggnFER'
            . 'jvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI1hY6RjcmVm2CpYJQABVRIgQljP/3j2'
            . 'E2l2l1Y/kmyR5dNXTS6iWuftktbr/COjiJ5kc2l6ZRknEGUkdHlwZWRibG9iaG1pbWVU'
            . 'eXBlamltYWdlL2pwZWc'
        );
        $expectedCid  = 'bafyreihldkhcwijkde7gx4rpkkuw7pl6lbyu5gieunyc7ihactn5bkd2nm';

        $cbor = $this->encoder->encode($input);

        $this->assertSame($expectedCbor, $cbor, 'ipld vector: CBOR bytes must match reference');
        $this->assertSame($expectedCid, CidUtil::computeForDagCbor($cbor), 'ipld vector: CID must match reference');

        // Round-trip
        $decoded = $this->decoder->decode($cbor);
        $this->assertEquals($input, $decoded);
    }

    /**
     * Vector "ipldArray" — array of four CidLinks.
     * Byte vector taken from vectors.ts.
     */
    public function testVectorIpldArrayBytesAndCid(): void
    {
        $input = [
            new CidLink('bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a'),
            new CidLink('bafyreigoxt64qghytzkr6ik7qvtzc7lyytiq5xbbrokbxjows2wp7vmo6q'),
            new CidLink('bafyreiaizynclnqiolq7byfpjjtgqzn4sfrsgn7z2hhf6bo4utdwkin7ke'),
            new CidLink('bafyreifd4w4tcr5tluxz7osjtnofffvtsmgdqcfrfi6evjde4pl27lrjpy'),
        ];

        // Byte array from vectors.ts, converted to binary via pack('C*', ...)
        // Hex-encoded reference bytes from vectors.ts (same as ipldArray)
        $expectedCbor = $this->hexBytes(
            '84d82a5825000171122065062a5a5a00fc16d73c6944237ccbc15b1c4a7234489336891d091741a239d0'
            . 'd82a58250001711220cebcfdc818f89e551f215f8567917d78c4d10edc218b941ba5d696acffd58ef4'
            . 'd82a5825000171122008ce1a25b60872e1f0e0af4a666865bc91632337f9d1ce5f05dca4c76521bf51'
            . 'd82a58250001711220a3e5b93147b35d2f9fba499b5c5296b3930c3808b12a3c4aa464e3d7afae297e'
        );
        $expectedCid  = 'bafyreiaj3udmqlqrcbjxjayzuxwp64gt64olcbjfrkldzoqponpru6gq4m';

        $cbor = $this->encoder->encode($input);

        $this->assertSame($expectedCbor, $cbor, 'ipldArray vector: CBOR bytes must match reference');
        $this->assertSame(
            $expectedCid,
            CidUtil::computeForDagCbor($cbor),
            'ipldArray vector: CID must match reference'
        );
    }

    /**
     * Vector "ipldNested" — deeply nested maps + arrays with CidLinks and CborBytes.
     * cbor_base64 and cid taken from data-model-fixtures.json.
     */
    public function testVectorIpldNestedBytesAndCid(): void
    {
        $cid1 = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $b1   = new CborBytes(base64_decode('nFERjvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI0'));
        $b2   = new CborBytes(base64_decode('iE+sPoHobU9tSIqGI+309LLCcWQIRmEXwxcoDt19tas'));

        $input = [
            'a' => [
                'b' => [
                    [
                        'd' => [new CidLink($cid1), new CidLink($cid1)],
                        'e' => [$b1, $b2],
                    ],
                ],
            ],
        ];

        $expectedCbor = base64_decode(
            'oWFhoWFigaJhZILYKlglAAFxEiBlBipaWgD8Ftc8aUQjfMvBWxxKcjRIkzaJHQkXQaI5'
            . '0NgqWCUAAXESIGUGKlpaAPwW1zxpRCN8y8FbHEpyNEiTNokdCRdBojnQYWWCWCCcURGO'
            . '8suLD2qbjkmuof1BPPILYu7Vdvid7r6wGsLMjVggiE+sPoHobU9tSIqGI+309LLCcWQI'
            . 'RmEXwxcoDt19tas'
        );
        $expectedCid  = 'bafyreid3imdulnhgeytpf6uk7zahjvrsqlofkmm5b5ub2maw4kqus6jp4i';

        $cbor = $this->encoder->encode($input);

        $this->assertSame($expectedCbor, $cbor, 'ipldNested vector: CBOR bytes must match reference');
        $this->assertSame(
            $expectedCid,
            CidUtil::computeForDagCbor($cbor),
            'ipldNested vector: CID must match reference'
        );

        $decoded = $this->decoder->decode($cbor);
        $this->assertEquals($input, $decoded);
    }

    /**
     * Vector "poorlyFormatted" — CID/bytes as plain strings, not as typed values.
     * Byte vector and CID taken from vectors.ts.
     */
    public function testVectorPoorlyFormattedBytesAndCid(): void
    {
        $input = [
            'a' => 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a',
            'b' => 'nFERjvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI0',
            'c' => [
                '$link'   => 'bafyreigoxt64qghytzkr6ik7qvtzc7lyytiq5xbbrokbxjows2wp7vmo6q',
                'another' => 'bad value',
            ],
            'd' => [
                '$bytes'  => 'nFERjvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI0',
                'another' => 'bad value',
            ],
            'e' => ['/' => 'bafyreigoxt64qghytzkr6ik7qvtzc7lyytiq5xbbrokbxjows2wp7vmo6q'],
            'f' => ['/' => ['bytes' => 'nFERjvLLiw9qm45JrqH9QTzyC2Lu1Xb4ne6+sBrCzI0']],
        ];

        // Hex-encoded reference bytes from vectors.ts (poorlyFormatted vector)
        $expectedCbor = $this->hexBytes(
            'a66161783b626166797265696466617976667577716137716c6e6f70646a697172787a7336626c6d'
            . '6f65753472756a636a746e63693562656c756469727a32616162782b6e4645526a764c4c69773971'
            . '6d34354a7271483951547a7943324c75315862346e65362b734272437a49306163a265246c696e6b'
            . '783b62616679726569676f7874363471676879747a6b7236696b377176747a63376c797974697135'
            . '786262726f6b62786a6f777332777037766d6f367167616e6f74686572696261642076616c756561'
            . '64a266246279746573782b6e4645526a764c4c697739716d34354a7271483951547a7943324c7531'
            . '5862346e65362b734272437a493067616e6f74686572696261642076616c75656165a1612f783b62'
            . '616679726569676f7874363471676879747a6b7236696b377176747a63376c797974697135786262'
            . '726f6b62786a6f777332777037766d6f36716166a1612fa1656279746573782b6e4645526a764c4c'
            . '697739716d34354a7271483951547a7943324c75315862346e65362b734272437a4930'
        );
        $expectedCid  = 'bafyreico7wgbbfe6dpfsuednrtrlh6t2yjl6xq5rf32gl3pgwhwxk77cn4';

        $cbor = $this->encoder->encode($input);

        $this->assertSame($expectedCbor, $cbor, 'poorlyFormatted vector: CBOR bytes must match reference');
        $this->assertSame(
            $expectedCid,
            CidUtil::computeForDagCbor($cbor),
            'poorlyFormatted vector: CID must match reference'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // cid.test.ts — CID utility assertions
    // ──────────────────────────────────────────────────────────────────────────

    public function testParseCidAcceptsValidDagCborCid(): void
    {
        $cid    = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $result = CidUtil::parseCid($cid);
        $this->assertSame($cid, $result);
    }

    public function testParseCidAcceptsValidRawCid(): void
    {
        $cid    = 'bafkreifjjcie6lypi6ny7amxnfftagclbuxndqonfipmb64f2km2devei4';
        $result = CidUtil::parseCid($cid);
        $this->assertSame($cid, $result);
    }

    public function testParseCidThrowsForInvalidString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CidUtil::parseCid('invalidcidstring');
    }

    public function testParseCidSafeReturnsValidCid(): void
    {
        $cid = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        $this->assertSame($cid, CidUtil::parseCidSafe($cid));
    }

    public function testParseCidSafeReturnsNullForInvalid(): void
    {
        $this->assertNull(CidUtil::parseCidSafe('invalidcidstring'));
    }

    public function testEnsureValidCidStringDoesNotThrowForValid(): void
    {
        $cid = 'bafyreidfayvfuwqa7qlnopdjiqrxzs6blmoeu4rujcjtnci5beludirz2a';
        CidUtil::ensureValidCidString($cid);
        $this->assertSame($cid, CidUtil::parseCid($cid)); // confirm it passes validation
    }

    public function testEnsureValidCidStringThrowsForInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid CID string/');
        CidUtil::ensureValidCidString('invalidcidstring');
    }

    public function testCidForKnownDataProducesExpectedCid(): void
    {
        // Verify the encoder + CidUtil pipeline against a known interop vector.
        // The basic fixture: encode -> sha256 -> CIDv1/dag-cbor/sha256 base32
        $input = [
            'string'  => 'abc',
            'unicode' => "a~\xc3\xb6\xc3\xb1\xc2\xa9\xe2\xbd\x98\xe2\x98\x8e"
                . "\xf0\x93\x8b\x93\xf0\x9f\x98\x80\xf0\x9f\x91\xa8"
                . "\xe2\x80\x8d\xf0\x9f\x91\xa9\xe2\x80\x8d\xf0\x9f\x91\xa7"
                . "\xe2\x80\x8d\xf0\x9f\x91\xa7",
            'integer' => 123,
            'bool'    => true,
            'null'    => null,
            'array'   => ['abc', 'def', 'ghi'],
            'object'  => [
                'string' => 'abc',
                'number' => 123,
                'bool'   => true,
                'arr'    => ['abc', 'def', 'ghi'],
            ],
        ];

        $cid = CidUtil::computeForDagCbor($this->encoder->encode($input));
        $this->assertSame('bafyreiclp443lavogvhj3d2ob2cxbfuscni2k5jk7bebjzg7khl3esabwq', $cid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function hexBytes(string $hex): string
    {
        $result = hex2bin($hex);
        if ($result === false) {
            throw new RuntimeException("hex2bin failed for input: {$hex}");
        }

        return $result;
    }
}
