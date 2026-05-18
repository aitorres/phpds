<?php

declare(strict_types=1);

namespace Tests\Domain\Repo;

use App\Domain\Repo\CidLink;
use Tests\TestCase;

class CidLinkTest extends TestCase
{
    public function testGetCidReturnsConstructorValue(): void
    {
        $link = new CidLink('bafyreigh2akiscaildc3o3vhbw5dvz25ftpqhmkfymf2e3glr3nzxqvxha');

        $this->assertSame(
            'bafyreigh2akiscaildc3o3vhbw5dvz25ftpqhmkfymf2e3glr3nzxqvxha',
            $link->getCid()
        );
    }
}
