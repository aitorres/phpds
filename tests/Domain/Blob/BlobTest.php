<?php

declare(strict_types=1);

namespace Tests\Domain\Blob;

use App\Domain\Blob\Blob;
use DateTimeImmutable;
use Tests\TestCase;

class BlobTest extends TestCase
{
    public function testGettersWithAllFields(): void
    {
        $createdAt = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $blob = new Blob(
            cid: 'bafy-blob',
            mimeType: 'image/png',
            size: 2048,
            tempKey: 'tmp-1',
            createdAt: $createdAt,
            takedownRef: 'tk-1',
        );

        $this->assertSame('bafy-blob', $blob->getCid());
        $this->assertSame('image/png', $blob->getMimeType());
        $this->assertSame(2048, $blob->getSize());
        $this->assertSame('tmp-1', $blob->getTempKey());
        $this->assertEquals($createdAt, $blob->getCreatedAt());
        $this->assertSame('tk-1', $blob->getTakedownRef());
    }

    public function testDefaultTakedownRefIsNullAndTempKeyMayBeNull(): void
    {
        $blob = new Blob(
            cid: 'bafy-blob',
            mimeType: 'image/png',
            size: 2048,
            tempKey: null,
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
        );

        $this->assertNull($blob->getTempKey());
        $this->assertNull($blob->getTakedownRef());
    }

    public function testJsonSerialize(): void
    {
        $blob = new Blob(
            cid: 'bafy-blob',
            mimeType: 'image/png',
            size: 2048,
            tempKey: 'tmp-1',
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00Z'),
            takedownRef: null,
        );

        $this->assertSame([
            'cid'         => 'bafy-blob',
            'mimeType'    => 'image/png',
            'size'        => 2048,
            'tempKey'     => 'tmp-1',
            'createdAt'   => '2026-01-01T00:00:00+00:00',
            'takedownRef' => null,
        ], $blob->jsonSerialize());
    }
}
