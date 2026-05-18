<?php

declare(strict_types=1);

namespace Tests\Domain\Record;

use App\Domain\Record\RecordBlob;
use Tests\TestCase;

class RecordBlobTest extends TestCase
{
    public function testGetters(): void
    {
        $rb = new RecordBlob(
            blobCid: 'bafy-blob',
            recordUri: 'at://did:plc:alice/app.bsky.feed.post/3k',
        );

        $this->assertSame('bafy-blob', $rb->getBlobCid());
        $this->assertSame('at://did:plc:alice/app.bsky.feed.post/3k', $rb->getRecordUri());
    }

    public function testJsonSerialize(): void
    {
        $rb = new RecordBlob(
            blobCid: 'bafy-blob',
            recordUri: 'at://did:plc:alice/app.bsky.feed.post/3k',
        );

        $this->assertSame([
            'blobCid'   => 'bafy-blob',
            'recordUri' => 'at://did:plc:alice/app.bsky.feed.post/3k',
        ], $rb->jsonSerialize());
    }
}
