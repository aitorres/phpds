<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Account\Password;

use App\Infrastructure\Account\Password\ScryptPasswordHasher;
use Tests\TestCase;

class ScryptPasswordHasherTest extends TestCase
{
    public function testHashAndVerifyRoundTrip(): void
    {
        $hasher = new ScryptPasswordHasher();

        $hash = $hasher->hash('correct horse battery staple');

        $this->assertNotSame('correct horse battery staple', $hash);
        $this->assertTrue($hasher->verify('correct horse battery staple', $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $hasher = new ScryptPasswordHasher();
        $hash = $hasher->hash('one');

        $this->assertFalse($hasher->verify('two', $hash));
    }

    public function testVerifyReturnsFalseForEmptyHash(): void
    {
        $hasher = new ScryptPasswordHasher();

        $this->assertFalse($hasher->verify('anything', ''));
    }

    public function testVerifyReturnsFalseForMalformedHash(): void
    {
        $hasher = new ScryptPasswordHasher();

        $this->assertFalse($hasher->verify('anything', 'not a real scrypt hash'));
    }

    public function testTwoHashesOfTheSamePasswordAreDistinctButBothVerify(): void
    {
        $hasher = new ScryptPasswordHasher();

        $a = $hasher->hash('hunter2');
        $b = $hasher->hash('hunter2');

        $this->assertNotSame($a, $b, 'hashes should be salted');
        $this->assertTrue($hasher->verify('hunter2', $a));
        $this->assertTrue($hasher->verify('hunter2', $b));
    }
}
