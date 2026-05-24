<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\MstKey;
use InvalidArgumentException;
use Tests\TestCase;

class MstKeyTest extends TestCase
{
    public function testValidatesAtprotoMstKeyShape(): void
    {
        $this->assertTrue(MstKey::isValid('app.bsky.feed.post/3jt7sst7vok2u'));
        $this->assertFalse(MstKey::isValid('missing-slash'));
        $this->assertFalse(MstKey::isValid('bad space/key'));
        $this->assertFalse(MstKey::isValid('app.bsky.feed.post/'));
    }

    public function testEnsureValidThrowsForInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a valid MST key: bad key/value');

        MstKey::ensureValid('bad key/value');
    }

    public function testCountsSharedPrefixLength(): void
    {
        $this->assertSame(16, MstKey::countSharedPrefix('bsky/posts/abcdefg', 'bsky/posts/abcdehi'));
        $this->assertSame(0, MstKey::countSharedPrefix('app/a', 'bpp/a'));
        $this->assertSame(5, MstKey::countSharedPrefix('same/', 'same/key'));
    }

    public function testLeadingZerosMatchesSha256ZeroPairCount(): void
    {
        $key = 'app.bsky.feed.post/3jt7sst7vok2u';
        $hash = hash('sha256', $key, true);

        $this->assertSame($this->countLeadingZeroPairs($hash), MstKey::leadingZeros($key));
    }

    private function countLeadingZeroPairs(string $hash): int
    {
        $leadingZeroBits = 0;

        for ($index = 0, $length = strlen($hash); $index < $length; $index++) {
            $byte = ord($hash[$index]);

            for ($bit = 7; $bit >= 0; $bit--) {
                if ((($byte >> $bit) & 1) === 0) {
                    $leadingZeroBits++;
                    continue;
                }

                return intdiv($leadingZeroBits, 2);
            }
        }

        return intdiv($leadingZeroBits, 2);
    }
}
