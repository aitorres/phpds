<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\UsedRefreshToken;
use App\Infrastructure\Persistence\OAuth\InMemoryUsedRefreshTokenRepository;
use Tests\TestCase;

class InMemoryUsedRefreshTokenRepositoryTest extends TestCase
{
    public function testExistsReturnsTrueForSeededTokens(): void
    {
        $repo = new InMemoryUsedRefreshTokenRepository([
            new UsedRefreshToken(1, 'refresh-a'),
        ]);

        $this->assertTrue($repo->exists('refresh-a'));
        $this->assertFalse($repo->exists('refresh-b'));
    }

    public function testSaveMakesTokenExist(): void
    {
        $repo = new InMemoryUsedRefreshTokenRepository();
        $repo->save(new UsedRefreshToken(1, 'refresh-a'));

        $this->assertTrue($repo->exists('refresh-a'));
    }

    public function testSaveOverwritesPriorEntryForSameRefreshTokenString(): void
    {
        $repo = new InMemoryUsedRefreshTokenRepository([
            new UsedRefreshToken(1, 'refresh-a'),
        ]);
        $repo->save(new UsedRefreshToken(2, 'refresh-a'));

        $this->assertTrue($repo->exists('refresh-a'));
        // deleting by the old tokenId should not remove the rebound entry
        $repo->deleteAllForTokenId(1);
        $this->assertTrue($repo->exists('refresh-a'));
    }

    public function testDeleteAllForTokenId(): void
    {
        $repo = new InMemoryUsedRefreshTokenRepository([
            new UsedRefreshToken(1, 'refresh-a'),
            new UsedRefreshToken(1, 'refresh-b'),
            new UsedRefreshToken(2, 'refresh-c'),
        ]);

        $repo->deleteAllForTokenId(1);

        $this->assertFalse($repo->exists('refresh-a'));
        $this->assertFalse($repo->exists('refresh-b'));
        $this->assertTrue($repo->exists('refresh-c'));
    }
}
