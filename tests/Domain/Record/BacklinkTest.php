<?php

declare(strict_types=1);

namespace Tests\Domain\Record;

use App\Domain\Record\Backlink;
use Tests\TestCase;

class BacklinkTest extends TestCase
{
    public function testGetters(): void
    {
        $backlink = new Backlink(
            uri: 'at://did:plc:alice/app.bsky.feed.like/abc',
            path: 'subject.uri',
            linkTo: 'at://did:plc:bob/app.bsky.feed.post/xyz',
        );

        $this->assertSame('at://did:plc:alice/app.bsky.feed.like/abc', $backlink->getUri());
        $this->assertSame('subject.uri', $backlink->getPath());
        $this->assertSame('at://did:plc:bob/app.bsky.feed.post/xyz', $backlink->getLinkTo());
    }

    public function testJsonSerialize(): void
    {
        $backlink = new Backlink(
            uri: 'at://did:plc:alice/app.bsky.feed.like/abc',
            path: 'subject.uri',
            linkTo: 'at://did:plc:bob/app.bsky.feed.post/xyz',
        );

        $this->assertSame([
            'uri'    => 'at://did:plc:alice/app.bsky.feed.like/abc',
            'path'   => 'subject.uri',
            'linkTo' => 'at://did:plc:bob/app.bsky.feed.post/xyz',
        ], $backlink->jsonSerialize());
    }
}
