<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizationRequest;
use App\Domain\OAuth\AuthorizationRequestNotFoundException;
use App\Infrastructure\Database\Database;
use App\Infrastructure\Database\Schema\AccountSchema;
use App\Infrastructure\Persistence\OAuth\SqliteAuthorizationRequestRepository;
use DateTimeImmutable;
use Tests\TestCase;

class SqliteAuthorizationRequestRepositoryTest extends TestCase
{
    private function newRepo(): SqliteAuthorizationRequestRepository
    {
        $db = new Database(':memory:');
        AccountSchema::apply($db);

        return new SqliteAuthorizationRequestRepository($db);
    }

    private function makeRequest(
        string $id = 'req-1',
        ?string $code = 'auth-code',
        ?DateTimeImmutable $expiresAt = null,
    ): AuthorizationRequest {
        return new AuthorizationRequest(
            id: $id,
            did: 'did:plc:alice',
            deviceId: 'dev-1',
            clientId: 'https://app.test/client.json',
            clientAuth: null,
            parameters: [],
            expiresAt: $expiresAt ?? new DateTimeImmutable('+1 hour'),
            code: $code,
        );
    }

    public function testFindByIdAndFindByCode(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRequest());

        $byId   = $repo->findById('req-1');
        $byCode = $repo->findByCode('auth-code');

        $this->assertSame('req-1', $byId->getId());
        $this->assertSame('req-1', $byCode->getId());
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('nope');
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRequest(code: null));

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findByCode('nope');
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRequest(id: 'req-1', code: 'a'));
        $repo->save($this->makeRequest(id: 'req-1', code: 'b'));

        $this->assertSame('b', $repo->findById('req-1')->getCode());
    }

    public function testDeleteById(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRequest());
        $repo->deleteById('req-1');

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('req-1');
    }

    public function testDeleteExpiredRemovesPastEntriesOnly(): void
    {
        $repo = $this->newRepo();
        $repo->save($this->makeRequest(id: 'expired', expiresAt: new DateTimeImmutable('-1 hour')));
        $repo->save($this->makeRequest(id: 'live', expiresAt: new DateTimeImmutable('+1 hour')));

        $removed = $repo->deleteExpired();

        $this->assertSame(1, $removed);
        $live = $repo->findById('live');
        $this->assertSame('live', $live->getId());

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('expired');
    }
}
