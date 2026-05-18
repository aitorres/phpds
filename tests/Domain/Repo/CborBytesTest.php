<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CborBytes;
use Tests\TestCase;

class CborBytesTest extends TestCase
{
    public function testGetBytesReturnsConstructorValue(): void
    {
        $raw = "\x00\x01\x02hello";
        $wrapped = new CborBytes($raw);

        $this->assertSame($raw, $wrapped->getBytes());
    }

    public function testSupportsEmptyByteString(): void
    {
        $wrapped = new CborBytes('');

        $this->assertSame('', $wrapped->getBytes());
    }
}
