<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Persistence\Account\AppPassword;

use App\Domain\Account\AppPassword\AppPassword;
use App\Domain\Account\AppPassword\AppPasswordNotFoundException;
use App\Infrastructure\Persistence\Account\AppPassword\InMemoryAppPasswordRepository;
use DateTimeImmutable;
use Tests\TestCase;

class InMemoryAppPasswordRepositoryTest extends TestCase
{
    private function makePassword(string $did = 'did:plc:alice', string $name = 'main'): AppPassword
    {
        return new AppPassword(
            did: $did,
            name: $name,
            passwordScrypt: 'hash',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );
    }

    public function testFindAllForDid(): void
    {
        $p1 = $this->makePassword(name: 'main');
        $p2 = $this->makePassword(name: 'ci');
        $repo = new InMemoryAppPasswordRepository([$p1, $p2]);

        $this->assertCount(2, $repo->findAllForDid('did:plc:alice'));
    }

    public function testFindByDidAndName(): void
    {
        $p = $this->makePassword();
        $repo = new InMemoryAppPasswordRepository([$p]);

        $this->assertSame($p, $repo->findByDidAndName('did:plc:alice', 'main'));
    }

    public function testFindThrowsWhenMissing(): void
    {
        $repo = new InMemoryAppPasswordRepository();

        $this->expectException(AppPasswordNotFoundException::class);
        $repo->findByDidAndName('did:plc:alice', 'nope');
    }

    public function testDeleteByDidAndName(): void
    {
        $p = $this->makePassword();
        $repo = new InMemoryAppPasswordRepository([$p]);
        $repo->deleteByDidAndName('did:plc:alice', 'main');

        $this->assertEmpty($repo->findAllForDid('did:plc:alice'));
    }
}
