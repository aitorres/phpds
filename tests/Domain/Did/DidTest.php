<?php

declare(strict_types=1);

namespace Tests\Domain\Did;

use App\Domain\Did\Did;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DidTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function validDidProvider(): array
    {
        return [
            'did:web with hostname'        => ['did:web:alice.pds.test'],
            'did:plc identifier'           => ['did:plc:abcdefghijklmnopqrstuvwx'],
            'did:web with port and path'   => ['did:web:example.com%3A8443:user:alice'],
            'unknown method still valid'   => ['did:example:123'],
            'method id with extra colons'  => ['did:web:host:with:many:colons'],
            'single char method and id'    => ['did:a:b'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidDidProvider(): array
    {
        return [
            'empty string'              => [''],
            'missing prefix'            => ['alice.pds.test'],
            'wrong prefix scheme'       => ['urn:web:alice.pds.test'],
            'prefix only'               => ['did:'],
            'prefix with empty parts'   => ['did::'],
            'missing identifier'        => ['did:web:'],
            'missing method'            => ['did::alice.pds.test'],
            'only two segments'         => ['did:web'],
            'whitespace prefix'         => [' did:web:alice.pds.test'],
            'wrong casing of prefix'    => ['DID:web:alice.pds.test'],
        ];
    }

    #[DataProvider('validDidProvider')]
    public function testIsValidAcceptsWellFormedDids(string $did): void
    {
        $this->assertTrue(Did::isValid($did), sprintf('Expected "%s" to be valid', $did));
    }

    #[DataProvider('invalidDidProvider')]
    public function testIsValidRejectsMalformedDids(string $did): void
    {
        $this->assertFalse(Did::isValid($did), sprintf('Expected "%s" to be invalid', $did));
    }

    public function testPrefixConstant(): void
    {
        $this->assertSame('did:', Did::PREFIX);
    }
}
