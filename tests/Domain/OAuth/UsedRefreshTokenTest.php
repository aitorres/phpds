<?php

declare(strict_types=1);

namespace Tests\Domain\OAuth;

use App\Domain\OAuth\UsedRefreshToken;
use Tests\TestCase;

class UsedRefreshTokenTest extends TestCase
{
    public function testGetters(): void
    {
        $used = new UsedRefreshToken(tokenId: 7, refreshToken: 'refresh-xyz');

        $this->assertSame(7, $used->getTokenId());
        $this->assertSame('refresh-xyz', $used->getRefreshToken());
    }

    public function testJsonSerialize(): void
    {
        $used = new UsedRefreshToken(tokenId: 7, refreshToken: 'refresh-xyz');

        $this->assertSame(
            ['tokenId' => 7, 'refreshToken' => 'refresh-xyz'],
            $used->jsonSerialize()
        );
    }
}
