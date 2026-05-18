<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\RepoBlock;
use Tests\TestCase;

class RepoBlockTest extends TestCase
{
    public function testGetters(): void
    {
        $block = new RepoBlock(
            cid: 'bafy-cid',
            repoRev: 'rev-1',
            size: 128,
            content: "\x00binary\xffbytes",
        );

        $this->assertSame('bafy-cid', $block->getCid());
        $this->assertSame('rev-1', $block->getRepoRev());
        $this->assertSame(128, $block->getSize());
        $this->assertSame("\x00binary\xffbytes", $block->getContent());
    }

    public function testJsonSerializeOmitsContent(): void
    {
        $block = new RepoBlock(
            cid: 'bafy-cid',
            repoRev: 'rev-1',
            size: 128,
            content: "\x00binary\xffbytes",
        );

        $payload = $block->jsonSerialize();

        $this->assertSame([
            'cid'     => 'bafy-cid',
            'repoRev' => 'rev-1',
            'size'    => 128,
        ], $payload);
        $this->assertArrayNotHasKey('content', $payload);
    }
}
