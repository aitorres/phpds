<?php

declare(strict_types=1);

namespace Tests\Domain\Sequencer;

use App\Domain\Repo\CarWriter;
use App\Domain\Repo\CborBytes;
use App\Domain\Repo\CidLink;
use App\Domain\Sequencer\SubscribeReposEventFactory;
use App\Infrastructure\Repo\NativeDagCborDecoder;
use App\Infrastructure\Repo\NativeDagCborEncoder;
use DateTimeImmutable;
use Tests\TestCase;

class SubscribeReposEventFactoryTest extends TestCase
{
    public function testGenesisCommitBuildsExpectedPayload(): void
    {
        $did = 'did:plc:alice';
        $commitCid = 'bafyreigh2akiscaildc3o3vhbw5dvz25ftpqhmkfymf2e3glr3nzxqvxha';
        $rev = '3l7w6m2k5xk2c';
        $blocks = [
            $commitCid => "\xa1dtestfblock1",
            'bafyreifz7c3sl6v4k7rb5q4xwzps5zz5g6t6x5xg4k4xw6dngj2x3fdtpi' => "\xa1dtestfblock2",
        ];
        $carBytes = "car-bytes\x00\x01";
        $time = new DateTimeImmutable('2026-01-01T12:34:56.789Z');

        $cars = $this->createMock(CarWriter::class);
        $cars->expects($this->once())
            ->method('write')
            ->with([$commitCid], $blocks)
            ->willReturn($carBytes);

        $factory = new SubscribeReposEventFactory(new NativeDagCborEncoder(), $cars);

        $payload = $this->decode($factory->genesisCommit($did, $commitCid, $rev, $blocks, $time));

        $this->assertSame(false, $payload['rebase']);
        $this->assertSame(false, $payload['tooBig']);
        $this->assertSame($did, $payload['repo']);
        $this->assertNull($payload['prev']);
        $this->assertSame($rev, $payload['rev']);
        $this->assertNull($payload['since']);
        $this->assertSame([], $payload['ops']);
        $this->assertSame([], $payload['blobs']);
        $this->assertSame('2026-01-01T12:34:56.789Z', $payload['time']);
        $this->assertInstanceOf(CidLink::class, $payload['commit']);
        $this->assertSame($commitCid, $payload['commit']->getCid());
        $this->assertInstanceOf(CborBytes::class, $payload['blocks']);
        $this->assertSame($carBytes, $payload['blocks']->getBytes());
    }

    public function testIdentityBuildsExpectedPayload(): void
    {
        $factory = new SubscribeReposEventFactory(new NativeDagCborEncoder(), $this->createStub(CarWriter::class));

        $payload = $this->decode($factory->identity(
            'did:plc:alice',
            'alice.test',
            new DateTimeImmutable('2026-01-01T12:34:56.789Z'),
        ));

        $this->assertSame([
            'did' => 'did:plc:alice',
            'time' => '2026-01-01T12:34:56.789Z',
            'handle' => 'alice.test',
        ], $payload);
    }

    public function testAccountBuildsPayloadWithoutStatusWhenOmitted(): void
    {
        $factory = new SubscribeReposEventFactory(new NativeDagCborEncoder(), $this->createStub(CarWriter::class));

        $payload = $this->decode($factory->account(
            'did:plc:alice',
            true,
            new DateTimeImmutable('2026-01-01T12:34:56.789Z'),
        ));

        $this->assertSame([
            'did' => 'did:plc:alice',
            'time' => '2026-01-01T12:34:56.789Z',
            'active' => true,
        ], $payload);
        $this->assertArrayNotHasKey('status', $payload);
    }

    public function testAccountIncludesStatusWhenProvided(): void
    {
        $factory = new SubscribeReposEventFactory(new NativeDagCborEncoder(), $this->createStub(CarWriter::class));

        $payload = $this->decode($factory->account(
            'did:plc:alice',
            false,
            new DateTimeImmutable('2026-01-01T12:34:56.789Z'),
            'takendown',
        ));

        $this->assertSame([
            'did' => 'did:plc:alice',
            'time' => '2026-01-01T12:34:56.789Z',
            'active' => false,
            'status' => 'takendown',
        ], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $bytes): array
    {
        $decoded = (new NativeDagCborDecoder())->decode($bytes);

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
