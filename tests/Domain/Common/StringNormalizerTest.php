<?php

declare(strict_types=1);

namespace Tests\Domain\Common;

use App\Domain\Common\StringNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StringNormalizerTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function normalizeHandleProvider(): array
    {
        return [
            'null stays null' => [null, null],
            'already normalized' => ['alice.pds.test', 'alice.pds.test'],
            'trim and lowercase' => ['  Alice.PDS.Test  ', 'alice.pds.test'],
            'tabs and newlines are trimmed' => ["\tBob.PDS.Test\n", 'bob.pds.test'],
            'whitespace only becomes empty' => ['   ', ''],
        ];
    }

    /**
     * @dataProvider normalizeHandleProvider
     */
    #[DataProvider('normalizeHandleProvider')]
    public function testNormalizeHandle(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, StringNormalizer::normalizeHandle($input));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function normalizeEmailProvider(): array
    {
        return [
            'already normalized' => ['alice@example.com', 'alice@example.com'],
            'trim and lowercase' => ['  Alice+Tag@Example.COM  ', 'alice+tag@example.com'],
            'tabs and newlines are trimmed' => ["\tbob@Example.COM\n", 'bob@example.com'],
            'whitespace only becomes empty' => ['   ', ''],
        ];
    }

    /**
     * @dataProvider normalizeEmailProvider
     */
    #[DataProvider('normalizeEmailProvider')]
    public function testNormalizeEmail(string $input, string $expected): void
    {
        $this->assertSame($expected, StringNormalizer::normalizeEmail($input));
    }
}
