<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\UsedRefreshToken;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\OAuth\SqliteUsedRefreshTokenRepository;
use Tests\TestCase;

class SqliteUsedRefreshTokenRepositoryTest extends TestCase
{
    private function newRepo(): SqliteUsedRefreshTokenRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteUsedRefreshTokenRepository($db);
    }

    public function testExistsReturnsTrueForSavedTokens(): void
    {
        $repo = $this->newRepo();
        $repo->save(new UsedRefreshToken(1, 'refresh-a'));

        $this->assertTrue($repo->exists('refresh-a'));
        $this->assertFalse($repo->exists('refresh-b'));
    }

    public function testSaveMakesTokenExist(): void
    {
        $repo = $this->newRepo();
        $repo->save(new UsedRefreshToken(1, 'refresh-a'));

        $this->assertTrue($repo->exists('refresh-a'));
    }

    public function testSaveOverwritesPriorEntryForSameRefreshTokenString(): void
    {
        $repo = $this->newRepo();
        $repo->save(new UsedRefreshToken(1, 'refresh-a'));
        $repo->save(new UsedRefreshToken(2, 'refresh-a'));

        $this->assertTrue($repo->exists('refresh-a'));
        // deleting by the old tokenId should not remove the rebound entry
        $repo->deleteAllForTokenId(1);
        $this->assertTrue($repo->exists('refresh-a'));
    }

    public function testDeleteAllForTokenId(): void
    {
        $repo = $this->newRepo();
        $repo->save(new UsedRefreshToken(1, 'refresh-a'));
        $repo->save(new UsedRefreshToken(1, 'refresh-b'));
        $repo->save(new UsedRefreshToken(2, 'refresh-c'));

        $repo->deleteAllForTokenId(1);

        $this->assertFalse($repo->exists('refresh-a'));
        $this->assertFalse($repo->exists('refresh-b'));
        $this->assertTrue($repo->exists('refresh-c'));
    }
}
