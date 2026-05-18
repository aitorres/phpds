<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\OAuth;

use App\Domain\OAuth\AuthorizationRequest;
use App\Domain\OAuth\AuthorizationRequestNotFoundException;
use App\Infrastructure\Persistence\OAuth\InMemoryAuthorizationRequestRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryAuthorizationRequestRepositoryTest extends TestCase
{
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
        $req = $this->makeRequest();
        $repo = new InMemoryAuthorizationRequestRepository([$req]);

        $this->assertSame($req, $repo->findById('req-1'));
        $this->assertSame($req, $repo->findByCode('auth-code'));
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = new InMemoryAuthorizationRequestRepository();

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('nope');
    }

    public function testFindByCodeThrowsWhenMissing(): void
    {
        $repo = new InMemoryAuthorizationRequestRepository([$this->makeRequest(code: null)]);

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findByCode('nope');
    }

    public function testSaveInsertsAndUpdates(): void
    {
        $repo = new InMemoryAuthorizationRequestRepository();
        $repo->save($this->makeRequest(id: 'req-1', code: 'a'));
        $repo->save($this->makeRequest(id: 'req-1', code: 'b'));

        $this->assertSame('b', $repo->findById('req-1')->getCode());
    }

    public function testDeleteById(): void
    {
        $repo = new InMemoryAuthorizationRequestRepository([$this->makeRequest()]);
        $repo->deleteById('req-1');

        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('req-1');
    }

    public function testDeleteExpiredRemovesPastEntriesOnly(): void
    {
        $past = $this->makeRequest(id: 'expired', expiresAt: new DateTimeImmutable('-1 hour'));
        $future = $this->makeRequest(id: 'live', expiresAt: new DateTimeImmutable('+1 hour'));
        $repo = new InMemoryAuthorizationRequestRepository([$past, $future]);

        $removed = $repo->deleteExpired();

        $this->assertSame(1, $removed);
        $this->assertSame($future, $repo->findById('live'));
        $this->expectException(AuthorizationRequestNotFoundException::class);
        $repo->findById('expired');
    }
}
